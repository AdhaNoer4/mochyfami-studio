<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Research\StoreSourceRequest;
use App\Http\Requests\Research\UpdateSourceRequest;
use App\Http\Resources\ResearchSourceResource;
use App\Models\ContentProject;
use App\Models\Source;
use App\Services\ResearchService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ResearchSourceController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ResearchService $researchService
    ) {}

    /**
     * List sources for the project research report.
     */
    public function index(ContentProject $project): JsonResponse
    {
        Gate::authorize('viewAny', Source::class);

        try {
            $sources = $this->researchService->listSources($project);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        return $this->successResponse(['items' => ResearchSourceResource::collection($sources)]);
    }

    /**
     * Store a new source for the project research report.
     */
    public function store(StoreSourceRequest $request, ContentProject $project): JsonResponse
    {
        Gate::authorize('create', Source::class);

        try {
            $source = $this->researchService->createSource($project, $request->validated());
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        return $this->successResponse(
            new ResearchSourceResource($source),
            'Source created successfully.',
            201
        );
    }

    /**
     * Update a source belonging to the project research report.
     */
    public function update(UpdateSourceRequest $request, ContentProject $project, Source $source): JsonResponse
    {
        Gate::authorize('update', $source);

        try {
            $updatedSource = $this->researchService->updateSource($project, $source, $request->validated());
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        return $this->successResponse(new ResearchSourceResource($updatedSource), 'Source updated successfully.');
    }

    /**
     * Delete a source belonging to the project research report.
     */
    public function destroy(ContentProject $project, Source $source): JsonResponse
    {
        Gate::authorize('delete', $source);

        try {
            $this->researchService->deleteSource($project, $source);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        return $this->successResponse(null, 'Source deleted successfully.');
    }
}
