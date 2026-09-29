<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\DuplicateVisualPlanItemOrderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\VisualPlan\ReorderVisualPlanItemsRequest;
use App\Http\Requests\VisualPlan\StoreVisualPlanItemRequest;
use App\Http\Requests\VisualPlan\UpdateVisualPlanItemRequest;
use App\Http\Resources\VisualPlanItemResource;
use App\Models\ContentProject;
use App\Services\VisualPlanService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class VisualPlanItemController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected VisualPlanService $visualPlanService
    ) {}

    /**
     * List the items of the visual plan ordered by their explicit order.
     */
    public function index(ContentProject $project, int $version): JsonResponse
    {
        try {
            $plan = $this->visualPlanService->getPlanOrFail($project, $version);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('view', $plan);

        return $this->successResponse(['items' => VisualPlanItemResource::collection(
            $this->visualPlanService->listItems($project, $version)
        )]);
    }

    /**
     * Store a new item on the visual plan.
     */
    public function store(StoreVisualPlanItemRequest $request, ContentProject $project, int $version): JsonResponse
    {
        try {
            $plan = $this->visualPlanService->getPlanOrFail($project, $version);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('update', $plan);

        try {
            $item = $this->visualPlanService->createItem($project, $version, $request->validated());
        } catch (DuplicateVisualPlanItemOrderException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        }

        return $this->successResponse(new VisualPlanItemResource($item), 'Visual plan item created successfully.', 201);
    }

    /**
     * Update an item belonging to the visual plan.
     */
    public function update(UpdateVisualPlanItemRequest $request, ContentProject $project, int $version, int $item): JsonResponse
    {
        try {
            $plan = $this->visualPlanService->getPlanOrFail($project, $version);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('update', $plan);

        try {
            $item = $this->visualPlanService->updateItem($project, $version, $item, $request->validated());
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        } catch (DuplicateVisualPlanItemOrderException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        }

        return $this->successResponse(new VisualPlanItemResource($item), 'Visual plan item updated successfully.');
    }

    /**
     * Delete an item belonging to the visual plan.
     */
    public function destroy(ContentProject $project, int $version, int $item): JsonResponse
    {
        try {
            $plan = $this->visualPlanService->getPlanOrFail($project, $version);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('update', $plan);

        try {
            $this->visualPlanService->deleteItem($project, $version, $item);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        return $this->successResponse(null, 'Visual plan item deleted successfully.');
    }

    /**
     * Deterministically reorder every item of the visual plan.
     */
    public function reorder(ReorderVisualPlanItemsRequest $request, ContentProject $project, int $version): JsonResponse
    {
        try {
            $plan = $this->visualPlanService->getPlanOrFail($project, $version);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('update', $plan);

        try {
            $this->visualPlanService->reorderItems($project, $version, $request->validated()['items']);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        }

        return $this->successResponse(null, 'Visual plan items reordered successfully.');
    }
}
