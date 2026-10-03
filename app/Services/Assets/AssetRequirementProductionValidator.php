<?php

namespace App\Services\Assets;

use App\Models\Asset;
use App\Models\AssetRequirement;

/**
 * Decides whether one requirement is ready to be consumed by production, and
 * says why not.
 *
 * The checks below are the things a production-ready requirement depends on:
 * a fulfilled status, a selected asset, an asset that still exists, one that
 * belongs to the same project, one that still has a file, one that is still a
 * candidate for this requirement, and one that passes the existing technical
 * compatibility evaluator. Each failure is a different problem with its own
 * stable code.
 *
 * This validator is the single place those structural rules live. Part 7
 * (fulfillment readiness) and Part 8 (production asset manifest) both answer
 * different questions about the same requirement, and letting each re-implement
 * the rules would let the two answers drift apart. It is read-only: it never
 * selects, fulfills, reopens, repairs, or writes anything.
 *
 * Technical compatibility is not redefined here. When the structural checks
 * pass, this defers to AssetCompatibilityEvaluator, which stays the one source
 * of truth for whether an asset technically fits a requirement.
 */
class AssetRequirementProductionValidator
{
    /**
     * The status a requirement must be in before it can be production-ready.
     */
    private const FULFILLED = 'fulfilled';

    public function __construct(
        protected AssetCompatibilityEvaluator $evaluator
    ) {}

    /**
     * Validate one requirement.
     *
     * The result carries the selected asset when one could be resolved, the
     * first blocking code and message when the requirement is invalid, and the
     * compatibility evaluation when one was performed. Compatibility issues
     * include warnings, so a valid result can still carry issues.
     *
     * @return array{
     *     valid: bool,
     *     asset: Asset|null,
     *     code: string|null,
     *     message: string|null,
     *     compatibility: array{compatible: bool, issues: array<int, array{code: string, severity: string, message: string, field: string}>}|null
     * }
     */
    public function validate(AssetRequirement $requirement): array
    {
        if ($requirement->status->value !== self::FULFILLED) {
            if ($requirement->selected_asset_id !== null) {
                return $this->invalid(
                    'REQUIREMENT_SELECTED_BUT_NOT_FULFILLED',
                    'Asset requirement has a selection but is not fulfilled.'
                );
            }

            return $this->invalid(
                'REQUIREMENT_NOT_FULFILLED',
                'Asset requirement has not been fulfilled.'
            );
        }

        if ($requirement->selected_asset_id === null) {
            return $this->invalid(
                'FULFILLED_REQUIREMENT_WITHOUT_SELECTION',
                'Asset requirement status is fulfilled but no asset is selected.'
            );
        }

        $asset = $requirement->selectedAsset;

        if ($asset === null) {
            return $this->invalid(
                'SELECTED_ASSET_MISSING',
                'Selected asset no longer exists in the database.'
            );
        }

        $project = $requirement->visualPlanItem?->visualPlan?->content_project_id;
        if ($project !== null && $asset->content_project_id !== $project) {
            return $this->invalid(
                'SELECTED_ASSET_CROSS_PROJECT',
                'Selected asset belongs to a different project.',
                $asset
            );
        }

        if (! $asset->hasFileAttached()) {
            return $this->invalid(
                'SELECTED_ASSET_HAS_NO_FILE',
                'Selected asset does not have an uploaded file.',
                $asset
            );
        }

        if ($requirement->assets->isNotEmpty() && ! $requirement->assets->pluck('id')->contains($asset->id)) {
            return $this->invalid(
                'SELECTED_ASSET_NOT_CANDIDATE',
                'Selected asset is no longer associated as a candidate for this requirement.',
                $asset
            );
        }

        $compatibility = $this->evaluator->evaluate($requirement, $asset);

        if (! $compatibility['compatible']) {
            return $this->invalid(
                'SELECTED_ASSET_INCOMPATIBLE',
                'The selected asset no longer satisfies this requirement.',
                $asset,
                $compatibility
            );
        }

        return [
            'valid' => true,
            'asset' => $asset,
            'code' => null,
            'message' => null,
            'compatibility' => $compatibility,
        ];
    }

    /**
     * @param  array{compatible: bool, issues: array<int, array{code: string, severity: string, message: string, field: string}>}|null  $compatibility
     * @return array{
     *     valid: bool,
     *     asset: Asset|null,
     *     code: string,
     *     message: string,
     *     compatibility: array{compatible: bool, issues: array<int, array{code: string, severity: string, message: string, field: string}>}|null
     * }
     */
    private function invalid(string $code, string $message, ?Asset $asset = null, ?array $compatibility = null): array
    {
        return [
            'valid' => false,
            'asset' => $asset,
            'code' => $code,
            'message' => $message,
            'compatibility' => $compatibility,
        ];
    }
}
