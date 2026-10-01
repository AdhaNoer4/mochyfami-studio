<?php

namespace App\Services\Assets;

use App\Enums\AssetRequirementStatus;
use App\Exceptions\AssetNotCandidateException;
use App\Exceptions\AssetNotSelectedException;
use App\Exceptions\AssetRequirementNotFulfillableException;
use App\Models\Asset;
use App\Models\AssetRequirement;
use App\Models\ContentProject;
use App\Services\AssetRequirementService;
use App\Services\AssetService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Decides which asset a requirement is fulfilled by, and keeps that decision
 * honest.
 *
 * Three states are kept apart here on purpose, because the API exposes them
 * apart and the database stores them apart:
 *
 *   - a candidate is an asset associated with the requirement, recorded in the
 *     asset_requirement_asset pivot by AssetRequirementService.
 *   - a selection is the one candidate the user chose, held in
 *     selected_asset_id.
 *   - a fulfillment is the requirement reaching the fulfilled status, which
 *     only happens after the selected asset is re-checked and passes.
 *
 * Moving between them is a deliberate step each time. Selecting does not
 * fulfill, because picking the closest candidate is not the same as declaring
 * it good enough. Clearing does not delete anything. And fulfilling does not
 * select, because the user already did that and a hidden second selection
 * would make the 422s misleading.
 */
class AssetRequirementFulfillmentService
{
    public function __construct(
        protected AssetRequirementService $assetRequirementService,
        protected AssetService $assetService,
        protected AssetCompatibilityEvaluator $evaluator
    ) {}

    /**
     * Report how one candidate stands against its requirement.
     *
     * This is a read, so it evaluates freely whatever the requirement's status
     * or current selection. Refusing to answer for a pending requirement would
     * hide exactly the information a user needs before deciding to start
     * searching.
     *
     * @return array{compatible: bool, issues: array<int, array{code: string, severity: string, message: string, field: string}>}
     *
     * @throws ModelNotFoundException
     */
    public function compatibility(
        ContentProject $project,
        int $versionNumber,
        int $itemId,
        int $requirementId,
        int $assetId
    ): array {
        $requirement = $this->resolveRequirement($project, $versionNumber, $itemId, $requirementId);
        $asset = $this->assetService->findAsset($project, $assetId);

        return $this->evaluator->evaluate($requirement, $asset);
    }

    /**
     * Choose one candidate as this requirement's asset.
     *
     * The asset has to already be a candidate. Selecting something that was
     * never offered as a fit would let a client attach an arbitrary asset by
     * skipping association, and association is what makes it auditable.
     *
     * Choosing a different candidate is allowed and replaces the previous
     * choice, since a user who has changed their mind should not have to clear
     * first. Re-choosing the current one is a no-op that still succeeds, so a
     * double-clicked button is not an error.
     *
     * Status is untouched. A requirement stays pending until it is explicitly
     * moved to searching, because having picked an asset says nothing about
     * whether anybody started looking.
     *
     * @throws AssetNotCandidateException
     * @throws ModelNotFoundException
     */
    public function select(
        ContentProject $project,
        int $versionNumber,
        int $itemId,
        int $requirementId,
        int $assetId
    ): AssetRequirement {
        $requirement = $this->resolveRequirement($project, $versionNumber, $itemId, $requirementId);
        $asset = $this->assetService->findAsset($project, $assetId);

        if ($requirement->selected_asset_id === $asset->getKey()) {
            return $requirement;
        }

        if (! $requirement->assets()->whereKey($asset->getKey())->exists()) {
            throw new AssetNotCandidateException(
                'This asset is not a candidate for this requirement. Associate it before selecting it.'
            );
        }

        $requirement->update(['selected_asset_id' => $asset->getKey()]);

        return $requirement->fresh();
    }

    /**
     * Drop the selection, keeping the candidates and the asset itself.
     *
     * A requirement that was fulfilled drops back to searching rather than
     * staying fulfilled with nothing behind it. That is the one direction out
     * of fulfilled the status graph allows, and it is why the graph is not
     * terminal.
     *
     * Clearing when nothing is selected succeeds and changes nothing. Treating
     * that as an error would make the operation unsafe to retry.
     *
     * @throws ModelNotFoundException
     */
    public function clearSelection(
        ContentProject $project,
        int $versionNumber,
        int $itemId,
        int $requirementId
    ): AssetRequirement {
        $requirement = $this->resolveRequirement($project, $versionNumber, $itemId, $requirementId);

        if ($requirement->selected_asset_id === null) {
            return $requirement;
        }

        return DB::transaction(function () use ($requirement) {
            $requirement->selected_asset_id = null;

            if ($requirement->status === AssetRequirementStatus::Fulfilled) {
                $requirement->status = AssetRequirementStatus::Searching;
            }

            $requirement->save();

            return $requirement->fresh();
        });
    }

    /**
     * Mark the requirement fulfilled against its current selection.
     *
     * The selected asset is re-evaluated here rather than trusted from when it
     * was selected. Between the two moments the file can have been replaced,
     * the asset can have been rejected, and the requirement's own target can
     * have been edited, so a pass at selection time is not evidence about now.
     * Evaluating at the moment of the decision is what makes the fulfilled
     * status mean something.
     *
     * Only searching can be fulfilled. Pending has not been picked up yet and
     * skipped was set aside, so either one is refused rather than quietly
     * advanced, since moving a requirement forward is the user's call to make
     * through the status endpoint.
     *
     * The whole thing runs in one transaction and validates before writing, so
     * a refusal leaves the selection and the status exactly as they were. There
     * is no partial fulfillment to clean up.
     *
     * @throws AssetNotSelectedException
     * @throws AssetRequirementNotFulfillableException
     * @throws ModelNotFoundException
     */
    public function fulfill(
        ContentProject $project,
        int $versionNumber,
        int $itemId,
        int $requirementId
    ): AssetRequirement {
        $requirement = $this->resolveRequirement($project, $versionNumber, $itemId, $requirementId);

        if ($requirement->status !== AssetRequirementStatus::Searching) {
            throw new AssetRequirementNotFulfillableException(
                "A requirement must be searching before it can be fulfilled. This one is {$requirement->status->value}.",
                [[
                    'code' => 'REQUIREMENT_NOT_SEARCHING',
                    'severity' => 'blocker',
                    'message' => "Only a searching requirement can be fulfilled. This one is {$requirement->status->label()}.",
                    'field' => 'status',
                ]]
            );
        }

        if ($requirement->selected_asset_id === null) {
            throw new AssetNotSelectedException(
                'Select an asset for this requirement before fulfilling it.'
            );
        }

        $asset = $this->assetService->findAsset($project, $requirement->selected_asset_id);

        $result = $this->evaluator->evaluate($requirement, $asset);

        if (! $result['compatible']) {
            throw new AssetRequirementNotFulfillableException(
                'The selected asset does not satisfy this requirement.',
                $result['issues']
            );
        }

        return DB::transaction(function () use ($requirement) {
            $requirement->status = AssetRequirementStatus::Fulfilled;

            $requirement->save();

            return $requirement->fresh();
        });
    }

    /**
     * Drop any fulfillment that depended on this asset, before it goes away.
     *
     * Called by AssetService while the asset still exists. The foreign key
     * would null the selection on its own, but a row left reading fulfilled
     * with no selection would be claiming something untrue, and the graph
     * cannot fix that later because the moment to notice has passed. So the
     * requirements are found and corrected while the link is still readable.
     *
     * Only fulfilled requirements are touched. A pending or searching
     * requirement that merely had this asset selected keeps its cleared
     * selection, which is a smaller change than reversing a status.
     *
     * @return int the number of requirements moved back out of fulfilled
     */
    public function releaseSelectionFromAsset(Asset $asset): int
    {
        $affected = AssetRequirement::query()
            ->where('selected_asset_id', $asset->getKey())
            ->where('status', AssetRequirementStatus::Fulfilled->value)
            ->update([
                'status' => AssetRequirementStatus::Searching->value,
                'updated_at' => now(),
            ]);

        return $affected;
    }

    /**
     * @throws ModelNotFoundException
     */
    private function resolveRequirement(
        ContentProject $project,
        int $versionNumber,
        int $itemId,
        int $requirementId
    ): AssetRequirement {
        return $this->assetRequirementService->findRequirement(
            $project,
            $versionNumber,
            $itemId,
            $requirementId
        );
    }
}
