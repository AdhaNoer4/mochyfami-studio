<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Research\StoreClaimRequest;
use App\Http\Requests\Research\UpdateClaimRequest;
use App\Http\Resources\ResearchClaimResource;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Services\ResearchService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ResearchClaimController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ResearchService $researchService
    ) {}

    /**
     * List claims for the project research report.
     */
    public function index(ContentProject $project): JsonResponse
    {
        Gate::authorize('viewAny', ResearchClaim::class);

        try {
            $claims = $this->researchService->listClaims($project);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        return $this->successResponse(['items' => ResearchClaimResource::collection($claims)]);
    }

    /**
     * Store a new claim for the project research report.
     */
    public function store(StoreClaimRequest $request, ContentProject $project): JsonResponse
    {
        Gate::authorize('create', ResearchClaim::class);

        try {
            $claim = $this->researchService->createClaim($project, $request->validated());
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        return $this->successResponse(
            new ResearchClaimResource($claim),
            'Claim created successfully.',
            201
        );
    }

    /**
     * Update a claim belonging to the project research report.
     */
    public function update(UpdateClaimRequest $request, ContentProject $project, ResearchClaim $claim): JsonResponse
    {
        Gate::authorize('update', $claim);

        try {
            $updatedClaim = $this->researchService->updateClaim($project, $claim, $request->validated());
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        return $this->successResponse(new ResearchClaimResource($updatedClaim), 'Claim updated successfully.');
    }

    /**
     * Delete a claim belonging to the project research report.
     */
    public function destroy(ContentProject $project, ResearchClaim $claim): JsonResponse
    {
        Gate::authorize('delete', $claim);

        try {
            $this->researchService->deleteClaim($project, $claim);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        return $this->successResponse(null, 'Claim deleted successfully.');
    }
}
