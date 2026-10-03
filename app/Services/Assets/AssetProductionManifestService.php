<?php

namespace App\Services\Assets;

use App\Models\Asset;
use App\Models\AssetRequirement;
use App\Models\VisualPlan;
use App\Models\VisualPlanItem;

/**
 * Builds the deterministic, read-only production asset manifest for one visual
 * plan.
 *
 * The manifest is not a new source of truth. The domain remains
 * AssetRequirement -> selected_asset_id -> Asset, and this is only a
 * production-facing projection of it: for each visual requirement, which
 * actual file would production consume? It never selects, fulfills, reopens,
 * repairs, analyzes media, or writes anything, and a GET request that produces
 * it changes no rows.
 *
 * Eligibility is decided by AssetRequirementProductionValidator, the same
 * collaborator Part 7 uses, so the two answers cannot drift apart. Technical
 * compatibility stays with AssetCompatibilityEvaluator. Only a requirement
 * that is fulfilled, structurally valid, and technically compatible becomes an
 * entry; everything else becomes an issue and is left out.
 *
 * This is the boundary before future production work. It deliberately contains
 * no FFmpeg, no transcoding, no trimming, no subtitles, no audio mixing, and
 * no timeline: those consume this manifest in a later phase.
 */
class AssetProductionManifestService
{
    public function __construct(
        protected AssetRequirementProductionValidator $validator
    ) {}

    /**
     * Compute the production manifest for one visual plan.
     *
     * @return array{
     *     ready: bool,
     *     summary: array{total_requirements: int, valid_entries: int, invalid_requirements: int},
     *     entries: array<int, array<string, mixed>>,
     *     issues: array<int, array<string, mixed>>
     * }
     */
    public function build(VisualPlan $plan): array
    {
        $plan->loadMissing([
            'items' => fn ($query) => $query->orderBy('order'),
            'items.assetRequirements' => fn ($query) => $query->orderBy('id'),
            'items.assetRequirements.selectedAsset',
            'items.assetRequirements.assets',
        ]);

        // The validator walks up from a requirement to its item and plan to
        // check project scope. Eloquent does not back-fill those inverse
        // relations when eager loading downward, so without this every
        // requirement would lazy-load its item and plan again. Wiring the
        // already loaded models into place keeps the manifest a fixed number
        // of queries rather than one per requirement.
        $plan->items->each(function (VisualPlanItem $item) use ($plan): void {
            $item->setRelation('visualPlan', $plan);

            $item->assetRequirements->each(function (AssetRequirement $requirement) use ($item): void {
                $requirement->setRelation('visualPlanItem', $item);
            });
        });

        $entries = [];
        $issues = [];
        $total = 0;
        $valid = 0;

        foreach ($plan->items as $item) {
            foreach ($item->assetRequirements as $requirement) {
                $total++;

                $context = [
                    'visual_plan_item_id' => $item->id,
                    'asset_requirement_id' => $requirement->id,
                ];

                $validation = $this->validator->validate($requirement);

                if (! $validation['valid']) {
                    $issues[] = $this->issue(
                        'blocker',
                        $validation['code'],
                        $validation['message'],
                        $context,
                        $requirement->selected_asset_id
                    );

                    if ($validation['compatibility'] !== null && $validation['asset'] !== null) {
                        foreach ($validation['compatibility']['issues'] as $detail) {
                            $issues[] = $this->issue(
                                $detail['severity'],
                                $detail['code'],
                                $detail['message'],
                                $context,
                                $validation['asset']->id,
                                $detail['field']
                            );
                        }
                    }

                    continue;
                }

                $valid++;
                $entries[] = $this->entry($item, $requirement, $validation['asset']);

                foreach ($validation['compatibility']['issues'] ?? [] as $detail) {
                    if ($detail['severity'] === 'warning') {
                        $issues[] = $this->issue(
                            'warning',
                            $detail['code'],
                            $detail['message'],
                            $context,
                            $validation['asset']->id,
                            $detail['field']
                        );
                    }
                }
            }
        }

        if ($total === 0) {
            $issues[] = $this->issue(
                'blocker',
                'NO_ASSET_REQUIREMENTS',
                'The visual plan has no asset requirements.',
                ['visual_plan_item_id' => null, 'asset_requirement_id' => null]
            );
        }

        $invalid = $total - $valid;

        return [
            'ready' => $total > 0 && $invalid === 0,
            'summary' => [
                'total_requirements' => $total,
                'valid_entries' => $valid,
                'invalid_requirements' => $invalid,
            ],
            'entries' => $entries,
            'issues' => $issues,
        ];
    }

    /**
     * A single production entry. It identifies the item, the requirement, and
     * the selected asset by id, and carries only stored metadata the asset
     * resource already publishes.
     *
     * There is no file_path here and no URL: where the file lives is an
     * internal detail, and production resolves it through the asset id.
     *
     * @return array<string, mixed>
     */
    private function entry(VisualPlanItem $item, AssetRequirement $requirement, Asset $asset): array
    {
        return [
            'visual_plan_item_id' => $item->id,
            'visual_plan_item_order' => $item->order,
            'asset_requirement_id' => $requirement->id,
            'requirement_type' => $requirement->requirement_type->value,
            'asset_id' => $asset->id,
            'asset_type' => $asset->type->value,
            'file_name' => $asset->file_name,
            'mime_type' => $asset->mime_type,
            'duration_seconds' => $asset->duration_seconds,
            'width' => $asset->width,
            'height' => $asset->height,
            'aspect_ratio' => $asset->aspect_ratio === null ? null : (float) $asset->aspect_ratio,
            'target_duration_seconds' => $requirement->target_duration_seconds,
            'status' => $requirement->status->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function issue(
        string $severity,
        string $code,
        string $message,
        array $context,
        ?int $selectedAssetId = null,
        ?string $field = null
    ): array {
        $issue = array_merge([
            'code' => $code,
            'severity' => $severity,
            'message' => $message,
        ], $context);

        if ($selectedAssetId !== null) {
            $issue['selected_asset_id'] = $selectedAssetId;
        }

        if ($field !== null) {
            $issue['field'] = $field;
        }

        return $issue;
    }
}
