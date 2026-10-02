<?php

namespace App\Services\Assets;

use App\Enums\AssetMetadataStatus;
use App\Models\Asset;
use App\Models\AssetRequirement;
use App\Models\VisualPlan;
use App\Models\VisualPlanItem;
use Illuminate\Support\Collection;

class AssetFulfillmentQualityService
{
    public function __construct(
        protected AssetCompatibilityEvaluator $evaluator
    ) {}

    public function evaluateVisualPlan(VisualPlan $plan): array
    {
        $plan->loadMissing(['scriptVersion.script', 'items.assetRequirements', 'items.assetRequirements.selectedAsset']);

        $items = $plan->items;
        $requirements = $items->flatMap(fn (VisualPlanItem $item) => $item->assetRequirements);

        $blockers = [];
        $warnings = [];
        $issues = [];

        if ($requirements->isEmpty()) {
            $blockers[] = $this->blocker('NO_ASSET_REQUIREMENTS', 'The visual plan has no asset requirements.');
            $summary = $this->emptySummary();
        } else {
            $summary = $this->buildSummary($requirements, $blockers, $warnings, $issues, $items);
        }

        return [
            'ready' => $blockers === [],
            'summary' => $summary,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'issues' => $issues,
        ];
    }

    private function buildSummary($requirements, array &$blockers, array &$warnings, array &$issues, $items): array
    {
        $fulfilled = 0;
        $unfulfilled = 0;
        $invalid = 0;
        $itemsById = $items->keyBy('id');

        foreach ($requirements as $requirement) {
            $item = $itemsById->get($requirement->visual_plan_item_id);
            $result = $this->evaluateRequirement($requirement, $item, $blockers, $warnings);
            $issues[] = $result['issue'];
            foreach ($result['details'] as $d) {
                $issues[] = $d;
            }
            if ($result['state'] === 'fulfilled') {
                $fulfilled++;
            } elseif ($result['state'] === 'invalid') {
                $invalid++;
            } else {
                $unfulfilled++;
            }
        }

        return [
            'total_requirements' => $requirements->count(),
            'fulfilled_requirements' => $fulfilled,
            'unfulfilled_requirements' => $unfulfilled,
            'invalid_fulfillments' => $invalid,
            'blocker_count' => count($blockers),
            'warning_count' => count($warnings),
        ];
    }

    private function emptySummary(): array
    {
        return [
            'total_requirements' => 0,
            'fulfilled_requirements' => 0,
            'unfulfilled_requirements' => 0,
            'invalid_fulfillments' => 0,
            'blocker_count' => 0,
            'warning_count' => 0,
        ];
    }
}
