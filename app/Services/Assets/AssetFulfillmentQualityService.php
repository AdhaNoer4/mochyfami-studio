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
        protected AssetCompatibilityEvaluator $evaluator
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
        $context = $this->context($requirement);

        if ($requirement->status->value === 'fulfilled') {
            $check = $this->checkFulfilledClaim($requirement, $context);
            if ($check['invalid']) {
                $blockers[] = $check['issue'];

                return [
                    'state' => 'invalid',
                    'issue' => $check['issue'],
                    'details' => $check['details'],
                ];
            }

            $asset = $requirement->selectedAsset;
            if ($asset !== null) {
                $result = $this->evaluator->evaluate($requirement, $asset);
                if (! $result['compatible']) {
                    $issue = $this->blocker(
                        'SELECTED_ASSET_INCOMPATIBLE',
                        'The selected asset no longer satisfies this requirement.',
                        $context + ['selected_asset_id' => $asset->id]
                    );
                    $blockers[] = $issue;

                    return [
                        'state' => 'invalid',
                        'issue' => $issue,
                        'details' => $this->mapDetails($result['issues'], $context, $asset),
                    ];
                }

                foreach ($result['issues'] as $issueDetail) {
                    if ($issueDetail['severity'] === 'warning') {
                        $warnings[] = $this->issue(
                            'warning',
                            $issueDetail['code'],
                            $issueDetail['message'],
                            $context + ['selected_asset_id' => $asset->id]
                        );
                    }
                }
            }

            return [
                'state' => 'fulfilled',
                'issue' => $this->info('REQUIREMENT_FULFILLED', 'Asset requirement is fulfilled.', $context),
                'details' => [],
            ];
        }

        if ($requirement->selected_asset_id !== null) {
            $context['selected_asset_id'] = $requirement->selected_asset_id;
            $issue = $this->blocker(
                'REQUIREMENT_SELECTED_BUT_NOT_FULFILLED',
                'Asset requirement has a selection but is not fulfilled.',
                $context
            );
            $blockers[] = $issue;

            return [
                'state' => 'unfulfilled',
                'issue' => $issue,
                'details' => [],
            ];
        }

        $issue = $this->blocker('REQUIREMENT_NOT_FULFILLED', 'Asset requirement has not been fulfilled.', $context);
        $blockers[] = $issue;

        return [
            'state' => 'unfulfilled',
            'issue' => $issue,
            'details' => [],
        ];
    }

    /**
     * Check if a requirement claiming to be fulfilled has any state inconsistency.
     *
     * @param  array<string, mixed>  $context
     * @return array{invalid: bool, issue: array<string, mixed>|null, details: array<int, array<string, mixed>>}
     */
    private function checkFulfilledClaim(AssetRequirement $requirement, array $context): array
    {
        if ($requirement->selected_asset_id === null) {
            return [
                'invalid' => true,
                'issue' => $this->blocker(
                    'FULFILLED_REQUIREMENT_WITHOUT_SELECTION',
                    'Asset requirement status is fulfilled but no asset is selected.',
                    $context
                ),
                'details' => [],
            ];
        }

        $context['selected_asset_id'] = $requirement->selected_asset_id;

        /** @var Asset|null $asset */
        $asset = $requirement->selectedAsset;

        if ($asset === null) {
            return [
                'invalid' => true,
                'issue' => $this->blocker(
                    'SELECTED_ASSET_MISSING',
                    'Selected asset no longer exists in the database.',
                    $context
                ),
                'details' => [],
            ];
        }

        $project = $requirement->visualPlanItem?->visualPlan?->content_project_id;
        if ($project !== null && $asset->content_project_id !== $project) {
            return [
                'invalid' => true,
                'issue' => $this->blocker(
                    'SELECTED_ASSET_CROSS_PROJECT',
                    'Selected asset belongs to a different project.',
                    $context
                ),
                'details' => [],
            ];
        }

        if (! $asset->hasFileAttached()) {
            return [
                'invalid' => true,
                'issue' => $this->blocker(
                    'SELECTED_ASSET_HAS_NO_FILE',
                    'Selected asset does not have an uploaded file.',
                    $context
                ),
                'details' => [],
            ];
        }

        if ($requirement->assets->isNotEmpty() && ! $requirement->assets->pluck('id')->contains($asset->id)) {
            return [
                'invalid' => true,
                'issue' => $this->blocker(
                    'SELECTED_ASSET_NOT_CANDIDATE',
                    'Selected asset is no longer associated as a candidate for this requirement.',
                    $context
                ),
                'details' => [],
            ];
        }

        return [
            'invalid' => false,
            'issue' => null,
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
