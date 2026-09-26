<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContentProject;
use App\Services\ResearchQualityService;
use App\Services\ResearchService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class ResearchQualityController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ResearchService $researchService,
        protected ResearchQualityService $qualityService
    ) {}

    /**
     * Evaluate the deterministic research quality / fact-check gate.
     */
    public function show(ContentProject $project): JsonResponse
    {
        try {
            $report = $this->researchService->getReportOrFail($project);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        $result = $this->qualityService->evaluateReport($report);

        return $this->successResponse($result);
    }
}
