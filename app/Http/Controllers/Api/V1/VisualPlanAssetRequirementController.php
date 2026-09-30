<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssetRequirementStatus;
use App\Exceptions\DuplicateAssetRequirementAssetException;
use App\Exceptions\InvalidAssetRequirementStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\VisualPlan\AttachAssetToRequirementRequest;
use App\Http\Requests\VisualPlan\StoreAssetRequirementRequest;
use App\Http\Requests\VisualPlan\UpdateAssetRequirementRequest;
use App\Http\Requests\VisualPlan\UpdateAssetRequirementStatusRequest;
use App\Http\Resources\AssetRequirementResource;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use App\Models\ContentProject;
use App\Models\VisualPlan;
use App\Services\AssetRequirementService;
use App\Services\VisualPlanService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

class VisualPlanAssetRequirementController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AssetRequirementService $assetRequirementService,
        protected VisualPlanService $visualPlanService
    ) {}

    /**
     * List the asset requirements of one visual plan item.
     */
    public function index(ContentProject $project, int $version, int $item): JsonResponse
    {
        $plan = $this->resolvePlan($project, $version);

        if ($plan === null) {
            return $this->errorResponse('Visual plan not found for this script version.', null, 404);
        }

        Gate::authorize('view', $plan);

        try {
            $requirements = $this->assetRequirementService->listRequirements($project, $version, $item);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        return $this->successResponse([
            'requirements' => AssetRequirementResource::collection($requirements),
        ]);
    }

    /**
     * Add a single asset requirement to a visual plan item.
     */
    public function store(StoreAssetRequirementRequest $request, ContentProject $project, int $version, int $item): JsonResponse
    {
        $plan = $this->resolvePlan($project, $version);

        if ($plan === null) {
            return $this->errorResponse('Visual plan not found for this script version.', null, 404);
        }

        Gate::authorize('update', $plan);

        try {
            $requirement = $this->assetRequirementService->createRequirement(
                $project,
                $version,
                $item,
                $request->validated()
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Unable to create the asset requirement.', null, 500);
        }

        return $this->successResponse(
            new AssetRequirementResource($requirement),
            'Asset requirement created successfully.',
            201
        );
    }

    /**
     * Update an asset requirement belonging to a visual plan item.
     */
    public function update(
        UpdateAssetRequirementRequest $request,
        ContentProject $project,
        int $version,
        int $item,
        int $requirement
    ): JsonResponse {
        $plan = $this->resolvePlan($project, $version);

        if ($plan === null) {
            return $this->errorResponse('Visual plan not found for this script version.', null, 404);
        }

        Gate::authorize('update', $plan);

        try {
            $updated = $this->assetRequirementService->updateRequirement(
                $project,
                $version,
                $item,
                $requirement,
                $request->validated()
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Unable to update the asset requirement.', null, 500);
        }

        return $this->successResponse(new AssetRequirementResource($updated), 'Asset requirement updated successfully.');
    }

    /**
     * Delete an asset requirement belonging to a visual plan item.
     */
    public function destroy(ContentProject $project, int $version, int $item, int $requirement): JsonResponse
    {
        $plan = $this->resolvePlan($project, $version);

        if ($plan === null) {
            return $this->errorResponse('Visual plan not found for this script version.', null, 404);
        }

        Gate::authorize('update', $plan);

        try {
            $this->assetRequirementService->deleteRequirement($project, $version, $item, $requirement);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        return $this->successResponse(null, 'Asset requirement deleted successfully.');
    }

    /**
     * List the assets associated with one asset requirement.
     *
     * This is the requirement's candidate list. The project's whole asset
     * library is deliberately not reachable from here.
     */
    public function assets(ContentProject $project, int $version, int $item, int $requirement): JsonResponse
    {
        $plan = $this->resolvePlan($project, $version);

        if ($plan === null) {
            return $this->errorResponse('Visual plan not found for this script version.', null, 404);
        }

        Gate::authorize('view', $plan);
        $this->authorizeProjectAssets($project, 'viewAny');

        try {
            $assets = $this->assetRequirementService->listRequirementAssets($project, $version, $item, $requirement);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        return $this->successResponse([
            'assets' => AssetResource::collection($assets),
        ]);
    }

    /**
     * Associate an existing asset with a requirement as a candidate.
     *
     * A second attach of the same pair is a 409, not a 500 and not a second
     * pivot row. Neither the requirement's status nor the asset's status is
     * touched: an association records a candidate and nothing more.
     */
    public function attachAsset(
        AttachAssetToRequirementRequest $request,
        ContentProject $project,
        int $version,
        int $item,
        int $requirement
    ): JsonResponse {
        $plan = $this->resolvePlan($project, $version);

        if ($plan === null) {
            return $this->errorResponse('Visual plan not found for this script version.', null, 404);
        }

        Gate::authorize('update', $plan);
        $this->authorizeProjectAssets($project, 'create');

        try {
            $asset = $this->assetRequirementService->attachAsset(
                $project,
                $version,
                $item,
                $requirement,
                $request->validated()['asset_id'],
            );
        } catch (DuplicateAssetRequirementAssetException $e) {
            return $this->errorResponse($e->getMessage(), null, 409);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Unable to associate the asset with the asset requirement.', null, 500);
        }

        return $this->successResponse(
            new AssetResource($asset),
            'Asset associated with the asset requirement successfully.',
            201
        );
    }

    /**
     * Remove an asset from a requirement's candidates.
     *
     * Only the association is removed. The asset and the requirement both
     * survive it, and detaching an asset that was never associated is a 404
     * rather than a silent success.
     */
    public function detachAsset(
        ContentProject $project,
        int $version,
        int $item,
        int $requirement,
        int $asset
    ): JsonResponse {
        $plan = $this->resolvePlan($project, $version);

        if ($plan === null) {
            return $this->errorResponse('Visual plan not found for this script version.', null, 404);
        }

        Gate::authorize('update', $plan);
        $this->authorizeProjectAssets($project, 'create');

        try {
            $this->assetRequirementService->detachAsset($project, $version, $item, $requirement, $asset);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        return $this->successResponse(null, 'Asset removed from the asset requirement successfully.');
    }

    /**
     * Require ownership of the project whose assets are being read or written.
     *
     * VisualPlanPolicy allows every authenticated user, so authorizing the plan
     * alone cannot answer "does this user own the project these assets belong
     * to". AssetPolicy is the policy in this repository that actually resolves
     * ownership, because an asset is reachable only through its project's
     * creator, so the project is authorized through it as well.
     *
     * The ability is 'create' for both writes. That is not a claim that
     * attaching is asset creation; AssetPolicy exposes exactly one
     * project-scoped write ability, and reusing it keeps the ownership check
     * in one place instead of spreading a second interpretation across the
     * policy. Correctness of the association itself is still the service's
     * job, not this gate's.
     *
     * The model class is passed ahead of the project on purpose. Authorizing
     * the project alone resolves ProjectPolicy, whose abilities all return
     * true, and the ownership check would silently never run.
     */
    private function authorizeProjectAssets(ContentProject $project, string $ability): void
    {
        Gate::authorize($ability, [Asset::class, $project]);
    }

    /**
     * Deterministically generate a pending requirement for every plan item
     * that has none yet. Never calls AI or any external provider.
     */
    public function generate(ContentProject $project, int $version): JsonResponse
    {
        $plan = $this->resolvePlan($project, $version);

        if ($plan === null) {
            return $this->errorResponse('Visual plan not found for this script version.', null, 404);
        }

        Gate::authorize('update', $plan);

        try {
            $result = $this->assetRequirementService->generateFromVisualPlan($project, $version);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Unable to generate asset requirements.', null, 500);
        }

        return $this->successResponse(
            $result,
            sprintf(
                'Asset requirement generation finished: %d created, %d already present, %d skipped.',
                $result['created'],
                $result['existing'],
                $result['skipped'],
            )
        );
    }

    /**
     * Move an asset requirement along the status pipeline.
     */
    public function transitionStatus(
        UpdateAssetRequirementStatusRequest $request,
        ContentProject $project,
        int $version,
        int $item,
        int $requirement
    ): JsonResponse {
        $plan = $this->resolvePlan($project, $version);

        if ($plan === null) {
            return $this->errorResponse('Visual plan not found for this script version.', null, 404);
        }

        Gate::authorize('update', $plan);

        try {
            $updated = $this->assetRequirementService->transitionStatus(
                $project,
                $version,
                $item,
                $requirement,
                AssetRequirementStatus::from($request->validated()['status'])
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        } catch (InvalidAssetRequirementStatusTransitionException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Unable to update the asset requirement status.', null, 500);
        }

        return $this->successResponse(
            new AssetRequirementResource($updated),
            'Asset requirement status updated successfully.'
        );
    }

    /**
     * Resolve the plan used for authorization, or null when it does not exist.
     */
    private function resolvePlan(ContentProject $project, int $version): ?VisualPlan
    {
        try {
            return $this->visualPlanService->getPlanOrFail($project, $version);
        } catch (ModelNotFoundException) {
            return null;
        }
    }
}
