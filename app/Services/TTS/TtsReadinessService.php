<?php

namespace App\Services\TTS;

use App\Enums\AssetMetadataStatus;
use App\Enums\TtsTrackStatus;
use App\Exceptions\TtsProviderException;
use App\Models\ContentProject;
use App\Models\TtsTrack;
use App\Models\VisualPlanItem;
use App\Services\VisualPlanService;

/**
 * Evaluates TTS readiness for one script version as a read-only projection.
 *
 * The report answers whether the version's visual plan has usable audio for
 * every item's current narration, which blockers prevent readiness, which
 * warnings need attention, and whether the configured provider resolves from
 * the registry. It never synthesizes audio, never writes a track or an asset,
 * and never mutates the plan; repeated calls with unchanged state return the
 * same projection.
 *
 * A track is history, not state: an item may carry many tracks, and only the
 * latest completed track whose narration hash matches the item's current
 * narration is usable audio. A failed attempt only blocks the item when it
 * belongs to that current narration and nothing usable supersedes it.
 */
class TtsReadinessService
{
    public function __construct(
        protected VisualPlanService $visualPlanService,
        protected TtsProviderRegistry $registry
    ) {}

    /**
     * Evaluate readiness for a project's exact script version.
     *
     * A missing script version resolves through VisualPlanService and raises a
     * 404 condition there; a missing Visual Plan is a readiness blocker, not
     * an error.
     *
     * @return array{
     *     ready: bool,
     *     blockers: array<int, array<string, mixed>>,
     *     warnings: array<int, array<string, mixed>>,
     *     info: array{total_items: int, ready_items: int, items_without_current_audio: int, completed_tracks: int, provider: array{name: string, available: bool}}
     * }
     */
    public function evaluate(ContentProject $project, int $versionNumber): array
    {
        $plan = $this->visualPlanService->getPlan($project, $versionNumber);

        $blockers = [];
        $warnings = [];
        $totalItems = 0;
        $readyItems = 0;
        $completedTracks = 0;

        if ($plan === null) {
            $blockers[] = $this->issue(
                'NO_VISUAL_PLAN',
                'The script version does not have a visual plan yet.'
            );
        } else {
            $plan->loadMissing(['scriptVersion', 'items.ttsTracks.asset']);

            $items = $plan->items->sortBy([
                ['order', 'asc'],
                ['id', 'asc'],
            ])->values();

            $totalItems = $items->count();

            if ($items->isEmpty()) {
                $blockers[] = $this->issue(
                    'NO_VISUAL_PLAN_ITEMS',
                    'The visual plan does not contain any items.'
                );
            } else {
                foreach ($items as $item) {
                    [$itemBlockers, $itemWarnings, $ready] = $this->evaluateItem($item);

                    $blockers = array_merge($blockers, $itemBlockers);
                    $warnings = array_merge($warnings, $itemWarnings);

                    if ($ready) {
                        $readyItems++;
                    }

                    $completedTracks += $item->ttsTracks
                        ->filter(fn (TtsTrack $track): bool => $track->status === TtsTrackStatus::Completed)
                        ->count();
                }
            }
        }

        $providerName = (string) config('tts.default_provider');
        $providerAvailable = $this->providerAvailable($providerName);

        if (! $providerAvailable) {
            $blockers[] = $this->issue(
                'PROVIDER_UNAVAILABLE',
                'The configured TTS provider is not available.'
            );
        }

        return [
            'ready' => $blockers === [],
            'blockers' => $blockers,
            'warnings' => $warnings,
            'info' => [
                'total_items' => $totalItems,
                'ready_items' => $readyItems,
                'items_without_current_audio' => $totalItems - $readyItems,
                'completed_tracks' => $completedTracks,
                'provider' => [
                    'name' => $providerName,
                    'available' => $providerAvailable,
                ],
            ],
        ];
    }

    /**
     * Classify one visual plan item into blockers, warnings and a per-item
     * ready flag.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: bool}
     */
    private function evaluateItem(VisualPlanItem $item): array
    {
        $narration = (string) $item->narration_text;
        $blockers = [];
        $warnings = [];

        if (trim($narration) === '') {
            $blockers[] = $this->itemIssue(
                'ITEM_WITHOUT_NARRATION',
                'A visual plan item has no usable narration text.',
                $item
            );

            return [$blockers, $warnings, false];
        }

        $currentHash = NarrationHasher::hash($narration);
        $usable = $this->currentUsableTrack($item, $currentHash);

        if ($usable !== null) {
            $this->addDurationMismatchWarning($warnings, $item, $usable);
            $this->addMetadataWarning($warnings, $item, $usable);
            $this->addLongNarrationWarning($warnings, $item, $narration);

            return [$blockers, $warnings, true];
        }

        $hasFailedCurrentAttempt = $item->ttsTracks->contains(
            fn (TtsTrack $track): bool => $track->status === TtsTrackStatus::Failed && $track->narration_hash === $currentHash
        );

        if ($hasFailedCurrentAttempt) {
            $blockers[] = $this->itemIssue(
                'TTS_GENERATION_FAILED',
                'TTS generation failed for the item\'s current narration.',
                $item
            );
        } else {
            $warnings[] = $this->itemIssue(
                'ITEM_WITHOUT_TRACK',
                'A visual plan item does not have usable audio for its current narration.',
                $item
            );
        }

        if ($this->hasStaleGeneratedHistory($item, $currentHash)) {
            $warnings[] = $this->itemIssue(
                'NARRATION_CHANGED_SINCE_SYNTHESIS',
                'The narration changed after a previous synthesis.',
                $item
            );
        }

        $this->addLongNarrationWarning($warnings, $item, $narration);

        return [$blockers, $warnings, false];
    }

    /**
     * The latest completed track whose narration hash matches the item's
     * current narration, or null when no such audio exists.
     *
     * A failed or archived track is never usable, and a stale completed track
     * stays invisible until its narration hash matches again.
     */
    private function currentUsableTrack(VisualPlanItem $item, string $currentHash): ?TtsTrack
    {
        return $item->ttsTracks
            ->filter(
                fn (TtsTrack $track): bool => $track->status === TtsTrackStatus::Completed && $track->narration_hash === $currentHash
            )
            ->sortByDesc('id')
            ->first();
    }

    /**
     * True when any previously generated track carries a narration hash that
     * differs from the item's current narration, meaning the audio that exists
     * is stale for what the item now says.
     *
     * A pending or generating attempt has not been generated yet, so it cannot
     * prove narration staleness on its own.
     */
    private function hasStaleGeneratedHistory(VisualPlanItem $item, string $currentHash): bool
    {
        return $item->ttsTracks->contains(
            fn (TtsTrack $track): bool => $track->narration_hash !== $currentHash
                && (
                    $track->status === TtsTrackStatus::Completed
                    || $track->status === TtsTrackStatus::Failed
                    || $track->status === TtsTrackStatus::Archived
                )
        );
    }

    /**
     * Emit nothing when the item or the usable track has no expected or
     * measured duration, and exact mismatch only otherwise.
     */
    private function addDurationMismatchWarning(array &$warnings, VisualPlanItem $item, TtsTrack $usable): void
    {
        if ($item->duration_seconds !== null
            && $usable->duration_seconds !== null
            && $item->duration_seconds !== $usable->duration_seconds) {
            $warnings[] = $this->itemIssue(
                'TRACK_DURATION_MISMATCH',
                'The audio duration does not match the item\'s expected duration.',
                $item
            );
        }
    }

    /**
     * A completed track is only trustworthy when its audio asset was actually
     * inspected and a verified duration was persisted, exactly like Part 2
     * stores it. A track status alone never counts as verification.
     */
    private function addMetadataWarning(array &$warnings, VisualPlanItem $item, TtsTrack $usable): void
    {
        $asset = $usable->asset;

        $verified = $asset !== null
            && $asset->metadata_status === AssetMetadataStatus::Completed
            && $asset->duration_seconds !== null;

        if (! $verified) {
            $warnings[] = $this->itemIssue(
                'METADATA_NOT_VERIFIED',
                'The audio asset metadata has not been verified.',
                $item
            );
        }
    }

    /**
     * Warn when the narration predicts more than 60 seconds of audio at the
     * locked three words per second heuristic. A warning never blocks.
     */
    private function addLongNarrationWarning(array &$warnings, VisualPlanItem $item, string $narration): void
    {
        $wordCount = count(preg_split('/\s+/', trim($narration)));

        if (ceil($wordCount / 3) > 60) {
            $warnings[] = $this->itemIssue(
                'LONG_NARRATION',
                'The narration is longer than the 60-second guidance.',
                $item
            );
        }
    }

    /**
     * Resolve the default provider without synthesizing anything.
     *
     * Resolution only builds the registered provider object, so this is a
     * purely local configuration check. An unknown provider name or a
     * misconfigured registration surfaces as unavailable, never as a crash.
     */
    private function providerAvailable(string $providerName): bool
    {
        try {
            $this->registry->resolve($providerName);

            return true;
        } catch (TtsProviderException) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function issue(string $code, string $message): array
    {
        return [
            'code' => $code,
            'message' => $message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemIssue(string $code, string $message, VisualPlanItem $item): array
    {
        return [
            'code' => $code,
            'message' => $message,
            'visual_plan_item_id' => $item->getKey(),
            'order' => $item->order,
        ];
    }
}
