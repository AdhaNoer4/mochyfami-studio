<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TtsReadinessResource;
use App\Models\ContentProject;
use App\Services\TTS\TtsReadinessService;
use App\Services\VisualPlanService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TtsReadinessController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected VisualPlanService $visualPlanService,
        protected TtsReadinessService $readinessService
    ) {}

    /**
     * Return the TTS readiness report for one script version.
     *
     * Read-only: the report is a projection that never synthesizes audio,
     * never mutates a track or asset, and never changes the visual plan. A
     * missing plan is a readiness blocker, not a 404; only a script version
     * that does not belong to the requested project is a 404.
     */
    public function show(ContentProject $project, int $version): JsonResponse
    {
        try {
            $plan = $this->visualPlanService->getPlan($project, $version);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('view', $plan ?? $project);

        $report = $this->readinessService->evaluate($project, $version);

        return $this->successResponse(new TtsReadinessResource($report));
    }
}
