<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssetProductionManifestResource;
use App\Models\ContentProject;
use App\Services\Assets\AssetProductionManifestService;
use App\Services\VisualPlanService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AssetProductionManifestController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected VisualPlanService $visualPlanService,
        protected AssetProductionManifestService $manifestService
    ) {}

    /**
     * Return the production asset manifest for a visual plan.
     *
     * Read-only: this projection never mutates data, never transitions a
     * status, and never selects, fulfills, or repairs anything.
     */
    public function show(ContentProject $project, int $version): JsonResponse
    {
        try {
            $plan = $this->visualPlanService->getPlanOrFail($project, $version);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('view', $plan);

        $manifest = $this->manifestService->build($plan);

        return $this->successResponse(new AssetProductionManifestResource($plan, $manifest));
    }
}
