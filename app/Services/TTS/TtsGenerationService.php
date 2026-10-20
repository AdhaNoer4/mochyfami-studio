<?php

namespace App\Services\TTS;

use App\Enums\AssetMetadataStatus;
use App\Enums\AssetType;
use App\Enums\TtsTrackSource;
use App\Enums\TtsTrackStatus;
use App\Exceptions\InvalidTtsNarrationException;
use App\Exceptions\TtsProviderException;
use App\Models\ContentProject;
use App\Models\TtsTrack;
use App\Models\VisualPlanItem;
use App\Services\AssetFileStorageService;
use App\Services\AssetMetadataService;
use App\Services\AssetService;
use App\Services\TTS\DTO\TtsRequest;
use App\Services\TTS\DTO\TtsResult;
use App\Services\VisualPlanService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Synthesizes a visual plan item's narration into a stored audio asset and
 * records the result as a TtsTrack.
 *
 * A track is history, not state: every request writes a new row and nothing
 * is ever overwritten, so a failed attempt and its successor stay visible
 * side by side. The narration is snapshotted onto the track byte for byte at
 * request time; the provider is later free to keep a different, newer
 * narration, which is exactly what staleness detection is for.
 *
 * The audio leaves the pipeline as an ordinary private Asset on the project's
 * asset library. From the moment it is stored it obeys every asset rule,
 * except that it can never become an asset requirement candidate; that last
 * rule is enforced by the requirement services, not here.
 */
class TtsGenerationService
{
    /**
     * The safe, displayable error message recorded on a track a synthesis
     * attempt did not survive. The provider's own exception message is never
     * stored: it can name internal services or carry credentials.
     */
    private const SAFE_ERROR_MESSAGE = 'TTS synthesis failed.';

    public function __construct(
        protected VisualPlanService $visualPlanService,
        protected TtsProviderRegistry $registry,
        protected AssetService $assetService,
        protected AssetFileStorageService $fileStorage,
        protected AssetMetadataService $metadata
    ) {}

    /**
     * List the automated synthesis history of one plan item, oldest first.
     *
     * Manual dubbing tracks share the table but not the story this endpoint
     * tells, so the listing is explicitly scoped to source = tts; the human
     * counterpart lives on ManualDubbingService::listManualTracks().
     *
     * @return Collection<int, TtsTrack>
     *
     * @throws ModelNotFoundException
     */
    public function listTracks(ContentProject $project, int $versionNumber, int $itemId): Collection
    {
        $item = $this->resolveItem($project, $versionNumber, $itemId);

        return $item->ttsTracks()
            ->where('source', TtsTrackSource::Tts)
            // The item's own current narration is what staleness is judged
            // against, and the asset is what a preview URL needs; both are
            // loaded here so the resource never fires a query per row.
            ->with(['visualPlanItem:id,narration_text', 'asset'])
            ->orderBy('id')
            ->get();
    }

    /**
     * The current narration's fingerprint, even before any track exists.
     *
     * The resource publishes this on the root of the list response so an
     * empty list carries the item's current fingerprint instead of a null
     * that could mean "no UI state" or "nothing synthesized yet".
     *
     * @throws ModelNotFoundException
     */
    public function currentNarrationHash(ContentProject $project, int $versionNumber, int $itemId): ?string
    {
        $narration = (string) $this->resolveItem($project, $versionNumber, $itemId)->narration_text;

        return $narration === '' ? null : NarrationHasher::hash($narration);
    }

    /**
     * Request synthesis of an item's narration.
     *
     * A narration the pipeline cannot handle is refused before any track is
     * written: no provider call, no history row, no orphaned state. An empty
     * narration and one exceeding the provider DTO's hard limit are the same
     * refusal, because both are "send something else", not failures of
     * synthesis.
     *
     * @throws InvalidTtsNarrationException
     * @throws ModelNotFoundException
     * @throws TtsProviderException
     */
    public function generate(
        ContentProject $project,
        int $versionNumber,
        int $itemId,
        array $data
    ): TtsTrack {
        $item = $this->resolveItem($project, $versionNumber, $itemId);
        $narration = (string) $item->narration_text;

        $this->assertNarrationUsable($narration);

        $track = $project->ttsTracks()->create([
            'visual_plan_item_id' => $item->getKey(),
            'provider' => (string) ($data['provider'] ?? config('tts.default_provider')),
            'model' => $data['model'] ?? null,
            'voice' => (string) $data['voice'],
            'language' => $data['language'] ?? null,
            'speed' => (float) ($data['speed'] ?? TtsRequest::DEFAULT_SPEED),
            'status' => TtsTrackStatus::Pending,
            'duration_seconds' => null,
            'narration_snapshot' => $narration,
            'narration_hash' => NarrationHasher::hash($narration),
            'error' => null,
        ]);

        try {
            $track->transitionTo(TtsTrackStatus::Generating);

            $result = $this->synthesizeFromProvider($track, $narration);
        } catch (InvalidArgumentException $e) {
            // The DTO's own validation duplicates ours; reaching it means the
            // two have drifted, so the request is refused rather than left on
            // a track half spun up.
            $this->failTrack($track, self::SAFE_ERROR_MESSAGE);

            throw new InvalidTtsNarrationException($e->getMessage(), 0, $e);
        } catch (TtsProviderException $e) {
            $this->failTrack($track, self::SAFE_ERROR_MESSAGE);

            throw $e;
        } catch (Throwable $e) {
            $this->failTrack($track, self::SAFE_ERROR_MESSAGE);

            throw $e;
        }

        return $this->storeTtsAsset($project, $track, $result);
    }

    /**
     * Request a brand new track for an item that already has one.
     *
     * The referenced track is used for nothing but scoping: it must exist on
     * the same item or the request is a 404. The new track is synthesized from
     * the item's current narration, so a changed narration produces a new
     * fingerprint, which is what lets the old one be detected as stale. The
     * previous track is never modified, in line with the history model.
     *
     * @throws InvalidTtsNarrationException
     * @throws ModelNotFoundException
     * @throws TtsProviderException
     */
    public function regenerate(
        ContentProject $project,
        int $versionNumber,
        int $itemId,
        int $trackId,
        array $data
    ): TtsTrack {
        $item = $this->resolveItem($project, $versionNumber, $itemId);

        $existing = $item->ttsTracks()->whereKey($trackId)->first();

        if (! $existing) {
            throw new ModelNotFoundException('TTS track not found for this visual plan item.');
        }

        return $this->generate($project, $versionNumber, $itemId, $data);
    }

    /**
     * Retire a track: move it to archived and stop there.
     *
     * Archiving is the opposite of deleting. The audio asset, its file, and
     * the provenance on the track's row all survive; a delete would take the
     * file and the asset with it and leave nothing but a history entry that
     * points at a null. The track's current status can always move to
     * archived, and an archived track is already there, so archiving twice is
     * a 422 rather than a quiet success.
     *
     * @throws InvalidTtsTrackStatusTransitionException
     * @throws ModelNotFoundException
     */
    public function archive(
        ContentProject $project,
        int $versionNumber,
        int $itemId,
        int $trackId
    ): TtsTrack {
        $item = $this->resolveItem($project, $versionNumber, $itemId);

        $track = $item->ttsTracks()->whereKey($trackId)->first();

        if (! $track) {
            throw new ModelNotFoundException('TTS track not found for this visual plan item.');
        }

        $track->transitionTo(TtsTrackStatus::Archived);

        return $track->fresh();
    }

    /**
     * Resolve a plan item through the full project -> script -> version -> plan
     * -> item chain, so an item from another project or another version is a
     * 404 rather than a cross-scope write.
     *
     * @throws ModelNotFoundException
     */
    private function resolveItem(ContentProject $project, int $versionNumber, int $itemId): VisualPlanItem
    {
        $plan = $this->visualPlanService->getPlanOrFail($project, $versionNumber);

        $item = $plan->items()->whereKey($itemId)->first();

        if (! $item) {
            throw new ModelNotFoundException('Visual plan item not found for this script version.');
        }

        return $item;
    }

    /**
     * @throws InvalidTtsNarrationException
     */
    private function assertNarrationUsable(string $narration): void
    {
        if (trim($narration) === '') {
            throw new InvalidTtsNarrationException('The narration for this visual plan item is empty.');
        }

        if (mb_strlen($narration) > TtsRequest::MAX_NARRATION_LENGTH) {
            throw new InvalidTtsNarrationException(
                sprintf('The narration must not exceed %d characters.', TtsRequest::MAX_NARRATION_LENGTH)
            );
        }
    }

    /**
     * @throws TtsProviderException
     */
    private function synthesizeFromProvider(TtsTrack $track, string $narration): TtsResult
    {
        return $this->registry
            ->resolve((string) $track->provider)
            ->synthesize(new TtsRequest(
                narrationText: $narration,
                voice: (string) $track->voice,
                model: $track->model,
                language: $track->language,
                speed: (float) $track->speed,
            ));
    }

    /**
     * Write the synthesized bytes into the asset library and record the
     * result on the track.
     *
     * The provider-declared duration is deliberately not trusted onto the
     * track. The stored file is measured and the track carries whatever the
     * measurement found, or nothing at all when the file could not be
     * measured, because a duration nobody verified is a claim without
     * evidence. Only a completed measurement produces a duration; anything
     * else leaves it null and the asset keeps its own failed metadata_status
     * for the user to retry with the Analyze action.
     *
     * Every failure after synthesis leaves the track failed and removes the
     * half-made asset, so a failed attempt never strands a useless file in
     * the library. The provider's exception (if any) propagates unchanged for
     * the resource to represent; nothing about it is written into storage.
     */
    private function storeTtsAsset(ContentProject $project, TtsTrack $track, TtsResult $result): TtsTrack
    {
        $tempPath = null;
        $asset = null;

        try {
            $tempPath = $this->writeTempAudio($result);

            $asset = $this->assetService->createAsset($project, [
                'type' => AssetType::Audio,
                'title' => 'TTS-generated audio',
                'description' => 'Audio synthesized from the visual plan narration.',
                'source_name' => 'tts',
            ]);

            $file = new UploadedFile($tempPath, 'tts.'.$result->extension, $result->mimeType, null, true);

            $this->fileStorage->replace($asset, $file);

            $asset = $this->metadata->analyse($asset->fresh() ?? $asset);

            $duration = $asset->metadata_status === AssetMetadataStatus::Completed
                ? $asset->duration_seconds
                : null;

            $track->transitionTo(TtsTrackStatus::Completed);
            $track->forceFill([
                'asset_id' => $asset->getKey(),
                'duration_seconds' => $duration,
                'error' => null,
            ])->save();
        } catch (Throwable $e) {
            $this->failTrack($track, self::SAFE_ERROR_MESSAGE);

            if ($asset !== null) {
                // The asset has a row and possibly a file, but no completed
                // track can ever point at it, so it can only sit there as an
                // orphan. Removing it keeps the library free of half-made
                // synthesis results.
                $this->assetService->deleteAsset($asset);
            }

            throw $e;
        } finally {
            if ($tempPath !== null && is_file($tempPath)) {
                @unlink($tempPath);
            }
        }

        return $track->fresh();
    }

    /**
     * A track whose synthesis attempt died gets the failed status when its
     * current status allows it, and always gets a safe, displayable error.
     *
     * The status branch is defensive: by the time this runs the track is
     * normally generating, but a caller that reaches a failed or terminal
     * track through some future path must not crash the error handling that
     * created it.
     */
    private function failTrack(TtsTrack $track, string $safeError): void
    {
        if ($track->status->canTransitionTo(TtsTrackStatus::Failed)) {
            $track->transitionTo(TtsTrackStatus::Failed);
        }

        $track->forceFill(['error' => $safeError])->save();
    }

    /**
     * Move the synthesized bytes onto disk so they can cross the storage
     * boundary as an UploadedFile, the same way a user upload does.
     *
     * @throws RuntimeException
     */
    private function writeTempAudio(TtsResult $result): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tts-');

        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary file for the synthesized audio.');
        }

        $written = file_put_contents($path, $result->audio);

        if ($written === false) {
            @unlink($path);

            throw new RuntimeException('Unable to write the synthesized audio to a temporary file.');
        }

        return $path;
    }
}
