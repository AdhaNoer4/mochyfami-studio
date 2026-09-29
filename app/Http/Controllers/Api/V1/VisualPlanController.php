<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\VisualPlanStatus;
use App\Exceptions\DuplicateVisualPlanException;
use App\Exceptions\InvalidVisualPlanStatusTransitionException;
use App\Exceptions\VisualPlanNotReadyException;
use App\Http\Controllers\Controller;
use App\Http\Requests\VisualPlan\StoreVisualPlanRequest;
use App\Http\Requests\VisualPlan\UpdateVisualPlanStatusRequest;
use App\Http\Resources\VisualPlanResource;
use App\Models\ContentProject;
use App\Models\VisualPlan;
use App\Services\VisualPlanService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

class VisualPlanController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected VisualPlanService $visualPlanService
    ) {}

    /**
     * Display the visual plan bound to an exact script version.
     */
    public function show(ContentProject $project, int $version): JsonResponse
    {
        try {
            $plan = $this->visualPlanService->getPlan($project, $version);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        if (! $plan) {
            return $this->successResponse(null, 'No visual plan yet for this script version.');
        }

        Gate::authorize('view', $plan);

        return $this->successResponse(new VisualPlanResource($plan));
    }

    /**
     * Create a visual plan for an explicit script version.
     *
     * The script must pass the existing readiness gate and the version must
     * belong to the project's script. A plan for the same version cannot be
     * duplicated. With create_from_script=true the plan is pre-populated from
     * the script sections.
     */
    public function store(StoreVisualPlanRequest $request, ContentProject $project, int $version): JsonResponse
    {
        Gate::authorize('create', VisualPlan::class);

        try {
            $plan = $request->boolean('create_from_script')
                ? $this->visualPlanService->createVisualPlanFromScript($project, $version, $request->validated())
                : $this->visualPlanService->createVisualPlan($project, $version, $request->validated());
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        } catch (DuplicateVisualPlanException $e) {
            return $this->errorResponse($e->getMessage(), null, 409);
        } catch (VisualPlanNotReadyException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        }

        $plan->load('scriptVersion', 'items');

        return $this->successResponse(new VisualPlanResource($plan), 'Visual plan created successfully.', 201);
    }

    /**
     * Transition the visual plan status.
     */
    public function transitionStatus(UpdateVisualPlanStatusRequest $request, ContentProject $project, int $version): JsonResponse
    {
        try {
            $plan = $this->visualPlanService->getPlanOrFail($project, $version);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('updateStatus', $plan);

        try {
            $updatedPlan = $this->visualPlanService->transitionStatus(
                $plan,
                VisualPlanStatus::from($request->validated()['status'])
            );
        } catch (InvalidVisualPlanStatusTransitionException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Unable to update visual plan status.', null, 500);
        }

        return $this->successResponse(new VisualPlanResource($updatedPlan), 'Visual plan status updated successfully.');
    }
}
