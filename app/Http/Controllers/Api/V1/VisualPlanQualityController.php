<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VisualPlanQualityResource;
use App\Models\ContentProject;
use App\Services\VisualPlan\VisualPlanQualityService;
use App\Services\VisualPlanService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class VisualPlanQualityController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected VisualPlanService $visualPlanService,
        protected VisualPlanQualityService $qualityService
    ) {}

    /**
     * Evaluate the deterministic visual plan production readiness gate.
     *
     * Read-only: the evaluation never mutates data, never transitions a status,
     * and never calls AI, external providers, or the network.
     */
    public function show(ContentProject $project, int $version): JsonResponse
    {
        try {
            $plan = $this->visualPlanService->getPlanOrFail($project, $version);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('view', $plan);

        $result = $this->qualityService->evaluateVisualPlan($plan);

        return $this->successResponse(new VisualPlanQualityResource($plan, $result));
    }
}
