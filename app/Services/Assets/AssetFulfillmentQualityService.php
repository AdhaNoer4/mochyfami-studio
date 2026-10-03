<?php

namespace App\Services\Assets;

use App\Models\Asset;
use App\Models\AssetRequirement;
use App\Models\VisualPlan;
use App\Models\VisualPlanItem;
use Illuminate\Support\Collection;

class AssetFulfillmentQualityService
{
    public function __construct(
        protected AssetRequirementProductionValidator $validator
    ) {}

    /**
     * Evaluate asset fulfillment readiness for one visual plan.
     *
     * @return array{
     *     ready: bool,
     *     score: int,
     *     summary: array<string, int>,
     *     items: array<int, array<string, mixed>>,
     *     blockers: array<int, array<string, mixed>>,
     *     warnings: array<int, array<string, mixed>>,
     *     issues: array<int, array<string, mixed>>
     * }
     */
    public function evaluateVisualPlan(VisualPlan $plan): array
    {
        $plan->loadMissing([
            'scriptVersion.script',
            'items.assetRequirements',
            'items.assetRequirements.selectedAsset',
            'items.assetRequirements.assets',
        ]);

        /** @var Collection<int, VisualPlanItem> $items */
        $items = $plan->items;

        /** @var Collection<int, AssetRequirement> $requirements */
        $requirements = $items->flatMap(function (VisualPlanItem $item) {
            return $item->assetRequirements;
        });

        $blockers = [];
        $warnings = [];
        $issues = [];
        $itemResults = [];

        if ($requirements->isEmpty()) {
            $blockers[] = $this->blocker('NO_ASSET_REQUIREMENTS', 'The visual plan has no asset requirements.');
            $issues[] = $blockers[0];
            $summary = $this->emptySummary();

            foreach ($items as $item) {
                $itemResults[] = [
                    'id' => $item->id,
                    'order' => $item->order,
                    'narration_text' => $item->narration_text,
                    'visual_prompt' => $item->visual_prompt,
                    'requirement_count' => 0,
                    'fulfilled_count' => 0,
                    'blockers' => [],
                    'ready' => false,
                ];
            }
        } else {
            $fulfilled = 0;
            $unfulfilled = 0;
            $invalid = 0;

            foreach ($items as $item) {
                $itemRequirements = $item->assetRequirements;
                $itemBlockers = [];
                $itemFulfilled = 0;

                foreach ($itemRequirements as $requirement) {
                    $result = $this->evaluateRequirement($requirement, $blockers, $warnings);

                    $issues[] = $result['issue'];
                    foreach ($result['details'] as $detail) {
                        $issues[] = $detail;
                    }

                    if ($result['state'] === 'fulfilled') {
                        $fulfilled++;
                        $itemFulfilled++;
                    } elseif ($result['state'] === 'invalid') {
                        $invalid++;
                        $itemBlockers[] = $result['issue'];
                    } else {
                        $unfulfilled++;
                        $itemBlockers[] = $result['issue'];
                    }
                }

                $itemResults[] = [
                    'id' => $item->id,
                    'order' => $item->order,
                    'narration_text' => $item->narration_text,
                    'visual_prompt' => $item->visual_prompt,
                    'requirement_count' => $itemRequirements->count(),
                    'fulfilled_count' => $itemFulfilled,
                    'blockers' => $itemBlockers,
                    'ready' => $itemBlockers === [],
                ];
            }

            $summary = [
                'total_requirements' => $requirements->count(),
                'fulfilled_requirements' => $fulfilled,
                'unfulfilled_requirements' => $unfulfilled,
                'invalid_fulfillments' => $invalid,
                'blocker_count' => count($blockers),
                'warning_count' => count($warnings),
            ];
        }

        $score = $this->calculateScore($summary);

        return [
            'ready' => $blockers === [],
            'score' => $score,
            'summary' => $summary,
            'items' => $itemResults,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'issues' => $issues,
        ];
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function calculateScore(array $summary): int
    {
        $total = $summary['total_requirements'] > 0 ? $summary['total_requirements'] : 1;
        $fulfilledRatio = $summary['fulfilled_requirements'] / $total;
        $invalidRatio = $summary['invalid_fulfillments'] > 0 ? 0 : 1.0;

        $score = ($fulfilledRatio * 80) + ($invalidRatio * 20);

        return (int) round(min(100, max(0, $score)));
    }

    /**
     * @return array<string, int>
     */
    private function emptySummary(): array
    {
        return [
            'total_requirements' => 0,
            'fulfilled_requirements' => 0,
            'unfulfilled_requirements' => 0,
            'invalid_fulfillments' => 0,
            'blocker_count' => 1,
            'warning_count' => 0,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $blockers
     * @param  array<int, array<string, mixed>>  $warnings
     * @return array{state: string, issue: array<string, mixed>, details: array<int, array<string, mixed>>}
     */
    private function evaluateRequirement(AssetRequirement $requirement, array &$blockers, array &$warnings): array
    {
        $baseContext = $this->context($requirement);
        $invalidContext = $baseContext;

        if ($requirement->selected_asset_id !== null) {
            $invalidContext['selected_asset_id'] = $requirement->selected_asset_id;
        }

        $validation = $this->validator->validate($requirement);

        if (! $validation['valid']) {
            $issue = $this->blocker($validation['code'], $validation['message'], $invalidContext);
            $blockers[] = $issue;

            $details = $validation['compatibility'] !== null && $validation['asset'] !== null
                ? $this->mapDetails($validation['compatibility']['issues'], $baseContext, $validation['asset'])
                : [];

            return [
                'state' => $requirement->status->value === 'fulfilled' ? 'invalid' : 'unfulfilled',
                'issue' => $issue,
                'details' => $details,
            ];
        }

        foreach ($validation['compatibility']['issues'] ?? [] as $issueDetail) {
            if ($issueDetail['severity'] === 'warning') {
                $warnings[] = $this->issue(
                    'warning',
                    $issueDetail['code'],
                    $issueDetail['message'],
                    $baseContext + ['selected_asset_id' => $validation['asset']?->id]
                );
            }
        }

        return [
            'state' => 'fulfilled',
            'issue' => $this->info('REQUIREMENT_FULFILLED', 'Asset requirement is fulfilled.', $baseContext),
            'details' => [],
        ];
    }

    /**
     * Map issues reported by AssetCompatibilityEvaluator to readiness issue details.
     *
     * @param  array<int, array{code: string, severity: string, message: string, field: string}>  $issues
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, mixed>>
     */
    private function mapDetails(array $issues, array $context, Asset $asset): array
    {
        $mapped = [];
        foreach ($issues as $issue) {
            $mapped[] = $this->issue(
                $issue['severity'],
                $issue['code'],
                $issue['message'],
                $context + [
                    'selected_asset_id' => $asset->id,
                    'field' => $issue['field'],
                ]
            );
        }

        return $mapped;
    }

    /**
     * @return array{visual_plan_item_id: int, asset_requirement_id: int}
     */
    private function context(AssetRequirement $requirement): array
    {
        return [
            'visual_plan_item_id' => $requirement->visual_plan_item_id,
            'asset_requirement_id' => $requirement->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function blocker(string $code, string $message, array $context = []): array
    {
        return $this->issue('blocker', $code, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function warning(string $code, string $message, array $context = []): array
    {
        return $this->issue('warning', $code, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function info(string $code, string $message, array $context = []): array
    {
        return $this->issue('info', $code, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function issue(string $severity, string $code, string $message, array $context = []): array
    {
        return array_merge([
            'code' => $code,
            'severity' => $severity,
            'message' => $message,
        ], $context);
    }
}
