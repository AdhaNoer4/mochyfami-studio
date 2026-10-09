<?php

namespace App\Services\TTS;

use App\Enums\AssetMetadataStatus;
use App\Models\Asset;
use App\Models\ContentProject;
use App\Services\ScriptService;

/**
 * Builds the deterministic, read-only TTS production manifest for a script
 * version's visual plan.
 *
 * The manifest is a data contract for future production work: every readiness
 * and current-track decision is delegated to TtsReadinessService so the
 * manifest and the readiness report can never disagree. This service only
 * reshapes that single projection into the manifest contract and never
 * re-implements a selection rule.
 */
class TtsManifestService
{
    public function __construct(
        protected TtsReadinessService $readinessService,
        protected ScriptService $scriptService
    ) {}

    /**
     * Build the manifest for a project's exact script version.
     *
     * The underlying resolveVersion raises the 404 condition for a missing or
     * foreign version; a missing visual plan is a manifest blocker, never an
     * error.
     *
     * @return array{
     *     ready: bool,
     *     script_version_id: int,
     *     visual_plan_id: int|null,
     *     items: array<int, array<string, mixed>>,
     *     blockers: array<int, array<string, mixed>>,
     *     warnings: array<int, array<string, mixed>>,
     *     info: array{total_items: int, items_with_audio: int, items_without_audio: int}
     * }
     */
    public function build(ContentProject $project, int $versionNumber): array
    {
        $projection = $this->readinessService->evaluateWithItems($project, $versionNumber);

        $items = array_map(
            fn (array $item): array => $this->manifestItem($item),
            $projection['items']
        );

        $itemsWithAudio = count(array_filter(
            $projection['items'],
            fn (array $item): bool => $item['usable'] !== null
        ));

        return [
            'ready' => $projection['ready'],
            'script_version_id' => $projection['script_version_id'] ?? $this->resolveScriptVersionId($project, $versionNumber),
            'visual_plan_id' => $projection['visual_plan_id'],
            'items' => $items,
            'blockers' => $projection['blockers'],
            'warnings' => $projection['warnings'],
            'info' => [
                'total_items' => $projection['info']['total_items'],
                'items_with_audio' => $itemsWithAudio,
                'items_without_audio' => $projection['info']['total_items'] - $itemsWithAudio,
            ],
        ];
    }

    /**
     * Shape one projected item into its manifest entry.
     *
     * audio.available mirrors the readiness per-item flag exactly: an item
     * with usable current audio is available even when its persisted metadata
     * is still unverified (that surfaces as a METADATA_NOT_VERIFIED warning and
     * the verified-only fields stay null). The segment fields for an available
     * entry follow the locked TTS manifest segment.
     *
     * @param  array{order: int, visual_plan_item_id: int, narration_hash: string, usable: TtsTrack|null, blockers: array<int, array<string, mixed>>, warnings: array<int, array<string, mixed>>, ready: bool}  $item
     * @return array<string, mixed>
     */
    private function manifestItem(array $item): array
    {
        $audio = [
            'available' => $item['usable'] !== null,
        ];

        if ($item['usable'] !== null) {
            $usable = $item['usable'];
            $verified = $this->metadataIsVerified($usable->asset);

            $audio += [
                'tts_track_id' => $usable->getKey(),
                'asset_id' => $usable->asset_id,
                'duration_seconds' => $verified ? $usable->asset?->duration_seconds : null,
                'mime_type' => $verified ? $usable->asset?->mime_type : null,
                'provider' => $usable->provider,
                'model' => $usable->model,
                'voice' => $usable->voice,
                'language' => $usable->language,
                'speed' => (float) $usable->speed,
            ];
        }

        return [
            'order' => $item['order'],
            'visual_plan_item_id' => $item['visual_plan_item_id'],
            'narration' => [
                'hash' => $item['narration_hash'],
            ],
            'audio' => $audio,
            'blockers' => $item['blockers'],
            'warnings' => $item['warnings'],
        ];
    }

    /**
     * The asset is verified exactly when TtsReadinessService treats its
     * metadata as verified, so the manifest and the readiness report share the
     * same predicate.
     */
    private function metadataIsVerified(?Asset $asset): bool
    {
        return $asset !== null
            && $asset->metadata_status === AssetMetadataStatus::Completed
            && $asset->duration_seconds !== null;
    }

    /**
     * Resolve the script version's primary key when the visual plan is absent.
     *
     * The readiness projection only carries script_version_id off the plan, so
     * the no-plan branch resolves it through the same project-scoped script
     * service chain VisualPlanService uses.
     */
    private function resolveScriptVersionId(ContentProject $project, int $versionNumber): int
    {
        $script = $this->scriptService->getScriptOrFail($project);

        return $this->scriptService->findVersion($script, $versionNumber)->getKey();
    }
}
