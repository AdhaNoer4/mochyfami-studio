<?php

namespace App\Services\VisualPlan;

use App\Enums\AssetRequirementAspectRatio;
use App\Enums\AssetRequirementStatus;
use App\Enums\AssetRequirementType;
use App\Enums\VisualPlanStatus;
use App\Models\AssetRequirement;
use App\Models\VisualPlan;
use App\Models\VisualPlanItem;
use App\Services\VisualPlanService;
use Illuminate\Support\Collection;

/**
 * Deterministic, read-only readiness gate for handing a visual plan over to
 * the Phase 4 Asset Manager.
 *
 * This service answers exactly one question: is the planning side complete
 * enough to start sourcing assets? It never claims an asset exists. It never
 * mutates anything, never transitions a status, never calls AI, and never
 * touches the network.
 *
 * It is deliberately separate from ScriptQualityService: research quality,
 * script quality, and visual plan quality are three gates over three different
 * layers, and merging them would produce one unfocused service.
 */
class VisualPlanQualityService
{
    /**
     * Requirement types Phase 4 will resolve from a stock provider, and which
     * therefore benefit from a search query.
     *
     * @var array<int, AssetRequirementType>
     */
    private const SEARCHABLE_TYPES = [
        AssetRequirementType::Video,
        AssetRequirementType::Image,
        AssetRequirementType::Audio,
    ];

    /**
     * Requirement types that are time-based, so a target duration matters.
     *
     * @var array<int, AssetRequirementType>
     */
    private const TEMPORAL_TYPES = [
        AssetRequirementType::Video,
        AssetRequirementType::Audio,
    ];

    /**
     * Documented score weights summing to 100. The score is informational
     * only: readiness is decided by the absence of blockers, never by a score
     * threshold.
     */
    private const SCORE_PLAN_COMPLETENESS = 30;

    private const SCORE_REQUIREMENT_COVERAGE = 30;

    private const SCORE_REQUIREMENT_VALIDITY = 20;

    private const SCORE_SEARCH_METADATA = 10;

    private const SCORE_DURATION = 10;

    /**
     * Evaluate one visual plan against the deterministic production rules.
     *
     * @return array{
     *     ready: bool,
     *     score: int,
     *     summary: array<string, int>,
     *     blockers: array<int, array<string, mixed>>,
     *     warnings: array<int, array<string, mixed>>,
     *     info: array<int, array<string, mixed>>
     * }
     */
    public function evaluateVisualPlan(VisualPlan $plan): array
    {
        $plan->loadMissing(['scriptVersion.script', 'items.assetRequirements']);

        $blockers = [];
        $warnings = [];
        $info = [];

        $items = $plan->items;
        $requirements = $items->flatMap(fn (VisualPlanItem $item) => $item->assetRequirements);

        $this->addPlanChecks($plan, $items->count(), $blockers);
        $summary = $this->buildSummary($items, $requirements, $blockers, $warnings);
        $this->addInfoChecks($plan, $blockers, $info);

        return [
            'ready' => $blockers === [],
            'score' => $this->calculateScore($items, $requirements, $summary),
            'summary' => $summary,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'info' => $info,
        ];
    }

    /**
     * Plan-level rules: the script version the plan hangs off, the plan
     * status, and whether the plan has any storyboard at all.
     *
     * @param  array<int, array<string, mixed>>  $blockers
     */
    private function addPlanChecks(VisualPlan $plan, int $itemCount, array &$blockers): void
    {
        if (! $this->hasValidScriptVersion($plan)) {
            $blockers[] = $this->issue(
                'INVALID_SCRIPT_VERSION_REFERENCE',
                'blocker',
                'The visual plan is not bound to a valid script version of this project.'
            );
        }

        if ($this->readPlanStatus($plan) === VisualPlanStatus::Archived->value) {
            $blockers[] = $this->issue(
                'ARCHIVED_VISUAL_PLAN',
                'blocker',
                'The visual plan is archived and is not considered ready for production.'
            );
        }

        if ($itemCount === 0) {
            $blockers[] = $this->issue(
                'EMPTY_VISUAL_PLAN',
                'blocker',
                'The visual plan has no items, so there is nothing to produce.'
            );
        }
    }

    /**
     * The plan must hang off a script version whose script belongs to this
     * project. A dangling reference is reported, never repaired.
     */
    private function hasValidScriptVersion(VisualPlan $plan): bool
    {
        $version = $plan->scriptVersion;

        if ($version === null) {
            return false;
        }

        return $version->script?->content_project_id === $plan->content_project_id;
    }

    /**
     * Walk every item and its requirements once, producing the summary plus the
     * per-item and per-requirement issues in a single pass.
     *
     * @param  Collection<int, VisualPlanItem>  $items
     * @param  Collection<int, AssetRequirement>  $requirements
     * @param  array<int, array<string, mixed>>  $blockers
     * @param  array<int, array<string, mixed>>  $warnings
     * @return array<string, int>
     */
    private function buildSummary(
        Collection $items,
        Collection $requirements,
        array &$blockers,
        array &$warnings
    ): array {
        $summary = [
            'total_items' => $items->count(),
            'items_with_requirements' => 0,
            'items_without_requirements' => 0,
            'total_requirements' => $requirements->count(),
            'valid_requirements' => 0,
            'invalid_requirements' => 0,
            'pending_requirements' => 0,
            'searching_requirements' => 0,
            'fulfilled_requirements' => 0,
            'skipped_requirements' => 0,
        ];

        foreach ($items as $item) {
            $itemRequirements = $item->assetRequirements;

            if ($itemRequirements->isEmpty()) {
                $summary['items_without_requirements']++;
                $blockers[] = $this->issue(
                    'ITEM_WITHOUT_ASSET_REQUIREMENT',
                    'blocker',
                    "Visual plan item #{$item->order} has no asset requirement.",
                    $item->id
                );

                continue;
            }

            $summary['items_with_requirements']++;

            $usable = 0;

            foreach ($itemRequirements as $requirement) {
                $type = $this->readType($requirement);
                $status = $this->readStatus($requirement);

                $this->countStatus($summary, $status);

                $valid = $this->addRequirementChecks($requirement, $type, $status, $item, $summary, $blockers, $warnings);

                if ($valid && $status !== AssetRequirementStatus::Skipped) {
                    $usable++;
                }
            }

            if ($usable === 0) {
                $blockers[] = $this->issue(
                    'NO_USABLE_ASSET_REQUIREMENT',
                    'blocker',
                    "Visual plan item #{$item->order} has no usable asset requirement for production.",
                    $item->id
                );
            }
        }

        $summary['blocker_count'] = count($blockers);
        $summary['warning_count'] = count($warnings);

        return $summary;
    }

    /**
     * @param  array<string, int>  $summary
     */
    private function countStatus(array &$summary, ?AssetRequirementStatus $status): void
    {
        match ($status) {
            AssetRequirementStatus::Pending => $summary['pending_requirements']++,
            AssetRequirementStatus::Searching => $summary['searching_requirements']++,
            AssetRequirementStatus::Fulfilled => $summary['fulfilled_requirements']++,
            AssetRequirementStatus::Skipped => $summary['skipped_requirements']++,
            default => null,
        };
    }

    /**
     * Validate one requirement and collect its blocker, if any, plus the
     * non-blocking warnings that only make sense once it is known to be valid.
     *
     * @param  array<string, int>  $summary
     * @param  array<int, array<string, mixed>>  $blockers
     * @param  array<int, array<string, mixed>>  $warnings
     * @return bool Whether the requirement itself is valid.
     */
    private function addRequirementChecks(
        AssetRequirement $requirement,
        ?AssetRequirementType $type,
        ?AssetRequirementStatus $status,
        VisualPlanItem $item,
        array &$summary,
        array &$blockers,
        array &$warnings
    ): bool {
        $description = trim((string) $requirement->getRawOriginal('description'));
        $rawAspectRatio = $requirement->getRawOriginal('aspect_ratio');
        $duration = $requirement->getRawOriginal('target_duration_seconds');

        $fault = $this->requirementFault($description, $duration, $rawAspectRatio);

        if ($type === null || $status === null) {
            $fault = 'type or status';
        }

        if ($fault !== null) {
            $summary['invalid_requirements']++;

            $blockers[] = $this->issue(
                $fault === 'placeholder' ? 'PLACEHOLDER_VISUAL_DESCRIPTION' : 'INVALID_ASSET_REQUIREMENT',
                'blocker',
                $fault === 'placeholder'
                    ? "Visual plan item #{$item->order} still has the placeholder visual description."
                    : "Visual plan item #{$item->order} has an invalid asset requirement ({$fault}).",
                $item->id,
                $requirement->id
            );

            return false;
        }

        $summary['valid_requirements']++;

        $this->addRequirementWarnings($requirement, $type, $status, $item, $warnings);

        return true;
    }

    /**
     * Describe why a requirement is unusable, or null when it is well formed.
     *
     * Values are read raw and validated with tryFrom() so a corrupt enum
     * column becomes a reported blocker instead of a ValueError. A 500 here
     * would hide the very data problem the gate exists to surface.
     */
    private function requirementFault(mixed $description, mixed $duration, mixed $aspectRatio): ?string
    {
        if ($description === '') {
            return 'empty description';
        }

        if ($description === VisualPlanService::PLACEHOLDER_VISUAL_PROMPT) {
            return 'placeholder';
        }

        if ($duration !== null && (int) $duration <= 0) {
            return 'invalid duration';
        }

        if ($aspectRatio !== null && AssetRequirementAspectRatio::tryFrom((string) $aspectRatio) === null) {
            return 'invalid aspect ratio';
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $warnings
     */
    private function addRequirementWarnings(
        AssetRequirement $requirement,
        AssetRequirementType $type,
        AssetRequirementStatus $status,
        VisualPlanItem $item,
        array &$warnings
    ): void {
        if ($status === AssetRequirementStatus::Searching) {
            $warnings[] = $this->issue(
                'ASSET_REQUIREMENT_SEARCHING',
                'warning',
                "A requirement on item #{$item->order} is still searching.",
                $item->id,
                $requirement->id
            );
        }

        if (in_array($type, self::SEARCHABLE_TYPES, true) && trim((string) $requirement->search_query) === '') {
            $warnings[] = $this->issue(
                'MISSING_ASSET_SEARCH_QUERY',
                'warning',
                "A {$type->label()} requirement on item #{$item->order} has no search query.",
                $item->id,
                $requirement->id
            );
        }

        if (in_array($type, self::TEMPORAL_TYPES, true) && $requirement->target_duration_seconds === null) {
            $warnings[] = $this->issue(
                'MISSING_TARGET_DURATION',
                'warning',
                "A {$type->label()} requirement on item #{$item->order} has no target duration.",
                $item->id,
                $requirement->id
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $blockers
     * @param  array<int, array<string, mixed>>  $info
     */
    private function addInfoChecks(VisualPlan $plan, array $blockers, array &$info): void
    {
        if ($blockers === []) {
            $info[] = $this->issue(
                'PLANNING_READY',
                'info',
                'The visual plan is ready to hand over to the Asset Manager.'
            );
        }

        if ($this->readPlanStatus($plan) !== VisualPlanStatus::Approved->value) {
            $status = $this->readPlanStatus($plan);
            $label = VisualPlanStatus::tryFrom((string) $status)?->label() ?? $status;

            $info[] = $this->issue(
                'PLAN_NOT_APPROVED',
                'info',
                $status === null
                    ? 'The visual plan has a status that is not a known value, so it cannot be Approved.'
                    : "The visual plan is {$label} rather than Approved."
            );
        }
    }

    /**
     * Read the plan status without touching the enum cast.
     *
     * visual_plans.status is a string column, so an unrecognized value is
     * storable. Comparing the raw value keeps the archived check total, and
     * keeps a corrupt status from becoming a 500 here.
     */
    private function readPlanStatus(VisualPlan $plan): ?string
    {
        $status = $plan->getRawOriginal('status');

        return $status === null ? null : (string) $status;
    }

    /**
     * Each component is a ratio scaled by its weight, so the score degrades
     * smoothly instead of jumping. A component with nothing to measure scores
     * zero rather than a free pass.
     *
     * @param  Collection<int, VisualPlanItem>  $items
     * @param  Collection<int, AssetRequirement>  $requirements
     * @param  array<string, int>  $summary
     */
    private function calculateScore(Collection $items, Collection $requirements, array $summary): int
    {
        $searchable = $requirements->filter(
            fn (AssetRequirement $requirement) => $this->isSearchable($this->readType($requirement))
        );
        $temporal = $requirements->filter(
            fn (AssetRequirement $requirement) => in_array($this->readType($requirement), self::TEMPORAL_TYPES, true)
        );

        $score = 0;
        $score += $this->ratio($items->filter(fn (VisualPlanItem $item) => $this->isItemSpecified($item))->count(), $summary['total_items'])
            * self::SCORE_PLAN_COMPLETENESS;
        $score += $this->ratio($summary['items_with_requirements'], $summary['total_items'])
            * self::SCORE_REQUIREMENT_COVERAGE;
        $score += $this->ratio($summary['valid_requirements'], $summary['total_requirements'])
            * self::SCORE_REQUIREMENT_VALIDITY;
        $score += $this->ratio(
            $searchable->filter(fn (AssetRequirement $requirement) => trim((string) $requirement->search_query) !== '')->count(),
            $searchable->count()
        ) * self::SCORE_SEARCH_METADATA;
        $score += $this->ratio(
            $temporal->filter(fn (AssetRequirement $requirement) => $requirement->target_duration_seconds !== null)->count(),
            $temporal->count()
        ) * self::SCORE_DURATION;

        return (int) round(min(100, max(0, $score)));
    }

    private function ratio(int $part, int $total): float
    {
        return $total > 0 ? $part / $total : 0.0;
    }

    private function isItemSpecified(VisualPlanItem $item): bool
    {
        return trim((string) $item->narration_text) !== '' && trim((string) $item->visual_prompt) !== '';
    }

    private function isSearchable(?AssetRequirementType $type): bool
    {
        return $type !== null && in_array($type, self::SEARCHABLE_TYPES, true);
    }

    /**
     * Read an enum-cast column defensively: a corrupt stored value would throw
     * when the cast is accessed, turning a data quality problem into a 500.
     */
    private function readType(AssetRequirement $requirement): ?AssetRequirementType
    {
        return AssetRequirementType::tryFrom((string) $requirement->getRawOriginal('requirement_type'));
    }

    private function readStatus(AssetRequirement $requirement): ?AssetRequirementStatus
    {
        return AssetRequirementStatus::tryFrom((string) $requirement->getRawOriginal('status'));
    }

    /**
     * Build a structured issue. Every issue identifies the offending plan item
     * and, where relevant, the requirement, so the UI never has to guess which
     * row a code refers to.
     *
     * @return array<string, mixed>
     */
    private function issue(
        string $code,
        string $severity,
        string $message,
        ?int $visualPlanItemId = null,
        ?int $assetRequirementId = null
    ): array {
        return [
            'code' => $code,
            'severity' => $severity,
            'message' => $message,
            'visual_plan_item_id' => $visualPlanItemId,
            'asset_requirement_id' => $assetRequirementId,
        ];
    }
}
