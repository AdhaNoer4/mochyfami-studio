<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContentProject;
use App\Services\ResearchPipelineService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ResearchPipelineController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ResearchPipelineService $pipeline
    ) {}

    /**
     * Evaluate the deterministic research execution pipeline.
     */
    public function show(ContentProject $project): JsonResponse
    {
        $report = $project->researchReport()->first();

        if (! $report) {
            return $this->errorResponse('Research report not found for this project.', null, 404);
        }

        Gate::authorize('view', $report);

        return $this->successResponse($this->pipeline->evaluate($report));
    }
}
