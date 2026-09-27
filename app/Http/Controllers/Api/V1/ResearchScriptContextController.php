<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContentProject;
use App\Services\Research\ResearchToScriptContextService;
use App\Services\ResearchService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ResearchScriptContextController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ResearchService $researchService,
        protected ResearchToScriptContextService $contextService
    ) {}

    /**
     * Resolve the authoritative research-to-script context.
     *
     * Read-only: it composes the existing quality and pipeline evaluations
     * and never mutates the research report or its claims.
     */
    public function show(ContentProject $project): JsonResponse
    {
        try {
            $report = $this->researchService->getReportOrFail($project);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('view', $report);

        return $this->successResponse($this->contextService->build($report)->toArray());
    }
}
