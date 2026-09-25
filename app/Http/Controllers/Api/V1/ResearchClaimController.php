<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\DuplicateEvidenceRelationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Research\StoreClaimRequest;
use App\Http\Requests\Research\UpdateClaimRequest;
use App\Http\Resources\ResearchClaimResource;
use App\Http\Resources\ResearchSourceResource;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\Source;
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

    /**
     * List sources attached as evidence to a claim.
     */
    public function sources(ContentProject $project, ResearchClaim $claim): JsonResponse
    {
        Gate::authorize('view', $claim);

        try {
            $sources = $this->researchService->getClaimSources($project, $claim);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        return $this->successResponse(['items' => ResearchSourceResource::collection($sources)]);
    }

    /**
     * Attach a source as evidence to a claim within the same research report.
     */
    public function attachSource(ContentProject $project, ResearchClaim $claim, Source $source): JsonResponse
    {
        Gate::authorize('attachSource', $claim);

        try {
            $claim = $this->researchService->attachSourceToClaim($project, $claim, $source);
        } catch (DuplicateEvidenceRelationException $e) {
            return $this->errorResponse($e->getMessage(), null, 409);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        return $this->successResponse(
            new ResearchClaimResource($claim),
            'Source attached to claim successfully.',
            201
        );
    }

    /**
     * Detach a source from a claim, removing only the evidence relationship.
     */
    public function detachSource(ContentProject $project, ResearchClaim $claim, Source $source): JsonResponse
    {
        Gate::authorize('detachSource', $claim);

        try {
            $this->researchService->detachSourceFromClaim($project, $claim, $source);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        return $this->successResponse(null, 'Source detached from claim successfully.');
    }
}
