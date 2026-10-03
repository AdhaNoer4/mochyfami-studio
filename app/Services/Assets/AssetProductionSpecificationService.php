<?php

namespace App\Services\Assets;

use App\Models\VisualPlan;
use App\Models\VisualPlanItem;

/**
 * The read-only production specification for a visual plan.
 *
 * Part 10 does not decide anything. Readiness and issues come verbatim from the
 * handoff, and the ordered segments come from the production asset manifest.
 * The only thing this layer adds is the production-facing packaging: each
 * manifest entry is enriched with the owning item's planned duration, and the
 * segments are summed into a small envelope. There is no timeline, no offset,
 * no transition, no render configuration, and no persistence - those belong to
 * a later phase that will consume this contract.
 *
 * Like the handoff, it invents no codes and no rules. If it grew its own
 * validation the manifest and handoff could disagree with it.
 */
class AssetProductionSpecificationService
{
    public function __construct(
        protected AssetProductionManifestService $manifest,
        protected AssetProductionHandoffService $handoff
    ) {}

    /**
     * Build the production specification for one visual plan.
     *
     * @return array{
     *     ready: bool,
     *     specification: array{
     *         segment_count: int,
     *         total_target_duration_seconds: int,
     *         total_planned_duration_seconds: int
     *     },
     *     segments: array<int, array<string, mixed>>,
     *     issues: array<int, array<string, mixed>>
     * }
     */
    public function evaluate(VisualPlan $plan): array
    {
        // Run the handoff first: it force-loads every relation the composed
        // authorities need, with the manifest ordering and the inverse
        // relations wired up. The manifest then sees them loaded (its
        // loadMissing() is a no-op), so the whole specification stays a fixed
        // number of queries instead of loading twice.
        $handoff = $this->handoff->evaluate($plan);
        $manifest = $this->manifest->build($plan);

        $items = $plan->items->keyBy('id');

        $segments = array_map(
            function (array $entry) use ($items): array {
                /** @var VisualPlanItem|null $item */
                $item = $items->get($entry['visual_plan_item_id']);

                return [
                    'visual_plan_item_id' => $entry['visual_plan_item_id'],
                    'visual_plan_item_order' => $entry['visual_plan_item_order'],
                    'asset_requirement_id' => $entry['asset_requirement_id'],
                    'requirement_type' => $entry['requirement_type'],
                    'asset_id' => $entry['asset_id'],
                    'asset_type' => $entry['asset_type'],
                    'duration_seconds' => $entry['duration_seconds'],
                    'target_duration_seconds' => $entry['target_duration_seconds'],
                    'planned_duration_seconds' => $item?->duration_seconds,
                ];
            },
            array_values($manifest['entries'])
        );

        return [
            'ready' => $handoff['ready'],
            'specification' => [
                'segment_count' => count($segments),
                'total_target_duration_seconds' => (int) array_sum(array_map(
                    fn (array $segment): int => (int) ($segment['target_duration_seconds'] ?? 0),
                    $segments
                )),
                'total_planned_duration_seconds' => (int) array_sum(array_map(
                    fn (array $segment): int => (int) ($segment['planned_duration_seconds'] ?? 0),
                    $segments
                )),
            ],
            'segments' => $segments,
            'issues' => $handoff['issues'],
        ];
    }
}
