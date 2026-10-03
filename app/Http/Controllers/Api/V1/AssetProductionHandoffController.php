<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssetProductionHandoffResource;
use App\Models\ContentProject;
use App\Services\Assets\AssetProductionHandoffService;
use App\Services\VisualPlanService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AssetProductionHandoffController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected VisualPlanService $visualPlanService,
        protected AssetProductionHandoffService $handoffService
    ) {}

    /**
     * Return production handoff readiness for a visual plan.
     *
     * Read-only: this projection composes the existing readiness layers and
     * never mutates data, never transitions a status, and never renders or
     * queues anything.
     */
    public function show(ContentProject $project, int $version): JsonResponse
    {
        try {
            $plan = $this->visualPlanService->getPlanOrFail($project, $version);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('view', $plan);

        $handoff = $this->handoffService->evaluate($plan);

        return $this->successResponse(new AssetProductionHandoffResource($plan, $handoff));
    }
}
