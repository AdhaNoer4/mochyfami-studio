<?php

namespace App\Services\Assets;

use App\Models\AssetRequirement;
use App\Models\VisualPlan;
use App\Models\VisualPlanItem;
use App\Services\VisualPlan\VisualPlanQualityService;

/**
 * Combines the existing readiness layers into one production handoff decision.
 *
 * Part 9 does not decide anything new. It asks the three authorities that
 * already answer their own questions - planning readiness, asset readiness,
 * and the production asset manifest - and reports whether their answers
 * together are enough to hand this visual plan to a future production
 * pipeline. It never selects, fulfills, repairs, transitions, renders, or
 * writes anything.
 *
 * The whole point is composition rather than re-implementation. If this class
 * ever grows its own compatibility or structural rules it has failed, because
 * the three upstream services would then be able to disagree with it. It
 * therefore consumes their results verbatim and only adds provenance
 * (`source`), aggregate counts, and the final boolean.
 */
class AssetProductionHandoffService
{
    /**
     * The stable order issue sources are aggregated in. Within a source the
     * upstream ordering is preserved, so two runs over the same data produce
     * the same list.
     */
    private const SOURCE_PLANNING = 'planning';

    private const SOURCE_ASSET_READINESS = 'asset_readiness';

    private const SOURCE_PRODUCTION_MANIFEST = 'production_manifest';

    public function __construct(
        protected VisualPlanQualityService $planningReadiness,
        protected AssetFulfillmentQualityService $assetReadiness,
        protected AssetProductionManifestService $manifest
    ) {}

    /**
     * Evaluate production handoff readiness for one visual plan.
     *
     * @return array{
     *     ready: bool,
     *     summary: array{
     *         planning_blockers: int,
     *         asset_blockers: int,
     *         manifest_blockers: int,
     *         total_blockers: int,
     *         total_warnings: int,
     *         production_entries: int
     *     },
     *     issues: array<int, array<string, mixed>>
     * }
     */
    public function evaluate(VisualPlan $plan): array
    {
        $this->preload($plan);

        $planning = $this->planningReadiness->evaluateVisualPlan($plan);
        $assets = $this->assetReadiness->evaluateVisualPlan($plan);
        $manifest = $this->manifest->build($plan);

        $planningBlockers = count($planning['blockers']);
        $assetBlockers = count($assets['blockers']);
        $manifestBlockers = $this->countSeverity($manifest['issues'], 'blocker');

        $totalBlockers = $planningBlockers + $assetBlockers + $manifestBlockers;
        $totalWarnings = count($planning['warnings'])
            + count($assets['warnings'])
            + $this->countSeverity($manifest['issues'], 'warning');

        $productionEntries = $manifest['summary']['valid_entries'];

        $issues = array_merge(
            $this->tagAll(self::SOURCE_PLANNING, $planning['blockers']),
            $this->tagAll(self::SOURCE_PLANNING, $planning['warnings']),
            $this->tagAll(self::SOURCE_ASSET_READINESS, $assets['blockers']),
            $this->tagAll(self::SOURCE_ASSET_READINESS, $assets['warnings']),
            $this->tagAll(self::SOURCE_PRODUCTION_MANIFEST, $manifest['issues']),
        );

        $ready = $planning['ready']
            && $assets['ready']
            && $manifest['ready']
            && $productionEntries > 0
            && $totalBlockers === 0;

        return [
            'ready' => $ready,
            'summary' => [
                'planning_blockers' => $planningBlockers,
                'asset_blockers' => $assetBlockers,
                'manifest_blockers' => $manifestBlockers,
                'total_blockers' => $totalBlockers,
                'total_warnings' => $totalWarnings,
                'production_entries' => $productionEntries,
            ],
            'issues' => $issues,
        ];
    }

    /**
     * Load every relation the three authorities touch, once, with the ordering
     * the manifest needs.
     *
     * The quality services call loadMissing() without an order, and the
     * manifest calls loadMissing() with one. Because loadMissing() is a no-op
     * once a relation is loaded, whichever service runs first fixes the order
     * for everyone. Loading here first makes that explicit: items arrive
     * ordered by their storyboard order and requirements by id, so the manifest
     * entries and the aggregated issues are deterministic, and no authority
     * lazy-loads a relation on a per-requirement basis.
     */
    private function preload(VisualPlan $plan): void
    {
        $plan->load([
            'scriptVersion.script',
            'items' => fn ($query) => $query->orderBy('order'),
            'items.assetRequirements' => fn ($query) => $query->orderBy('id'),
            'items.assetRequirements.selectedAsset',
            'items.assetRequirements.assets',
        ]);

        // The validator walks up from a requirement to its item and plan for the
        // project-scope check. Eloquent does not back-fill those inverse
        // relations when eager loading downward, so without this every
        // requirement - in all three authorities - would lazy-load them again.
        // Wiring the already loaded models into place keeps the whole handoff a
        // fixed number of queries rather than one per requirement.
        $plan->items->each(function (VisualPlanItem $item) use ($plan): void {
            $item->setRelation('visualPlan', $plan);

            $item->assetRequirements->each(function (AssetRequirement $requirement) use ($item): void {
                $requirement->setRelation('visualPlanItem', $item);
            });
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $issues
     * @return array<int, array<string, mixed>>
     */
    private function tagAll(string $source, array $issues): array
    {
        return array_map(
            fn (array $issue): array => array_merge(['source' => $source], $issue),
            array_values($issues)
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $issues
     */
    private function countSeverity(array $issues, string $severity): int
    {
        return count(array_filter(
            $issues,
            fn (array $issue): bool => ($issue['severity'] ?? null) === $severity
        ));
    }
}
