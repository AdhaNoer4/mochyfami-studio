<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\SearchProviderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Research\ResearchDiscoveryRequest;
use App\Models\ContentProject;
use App\Services\Research\DTO\SearchQuery;
use App\Services\ResearchIntelligenceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ResearchDiscoveryController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ResearchIntelligenceService $intelligenceService
    ) {}

    /**
     * Discover candidate sources for the project research report.
     *
     * Read-only with respect to the database: results are never persisted
     * and never automatically attached to claims.
     */
    public function discover(ResearchDiscoveryRequest $request, ContentProject $project): JsonResponse
    {
        $report = $project->researchReport()->first();

        if (! $report) {
            return $this->errorResponse('Research report not found for this project.', null, 404);
        }

        Gate::authorize('view', $report);

        $data = $request->validated();

        $query = new SearchQuery(
            $data['query'],
            $data['max_results'] ?? SearchQuery::DEFAULT_MAX_RESULTS,
        );

        try {
            $response = $this->intelligenceService->discover($report, $query, $data['provider'] ?? null);
        } catch (SearchProviderException $e) {
            return $this->errorResponse('The search provider could not complete the request.', null, 502);
        }

        return $this->successResponse($response->toArray());
    }
}
