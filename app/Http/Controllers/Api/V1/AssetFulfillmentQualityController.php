<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssetFulfillmentQualityResource;
use App\Models\ContentProject;
use App\Services\Assets\AssetFulfillmentQualityService;
use App\Services\VisualPlanService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AssetFulfillmentQualityController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected VisualPlanService $visualPlanService,
        protected AssetFulfillmentQualityService $qualityService
    ) {}

    /**
     * Evaluate asset fulfillment readiness for a visual plan.
     *
     * Read-only: this evaluation never mutates data, never transitions a status,
     * and never calls AI or external services.
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

        return $this->successResponse(new AssetFulfillmentQualityResource($plan, $result));
    }
}
