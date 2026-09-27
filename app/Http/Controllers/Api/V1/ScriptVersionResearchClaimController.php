<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\DuplicateScriptResearchClaimException;
use App\Http\Controllers\Controller;
use App\Http\Resources\ScriptVersionResearchClaimResource;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Services\Script\ScriptResearchTraceabilityService;
use App\Services\ScriptService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ScriptVersionResearchClaimController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ScriptService $scriptService,
        protected ScriptResearchTraceabilityService $traceabilityService,
    ) {}

    /**
     * List the research claims mapped to a script version, with summary.
     */
    public function index(ContentProject $project, int $version): JsonResponse
    {
        try {
            $script = $this->scriptService->getScriptOrFail($project);
            $scriptVersion = $this->scriptService->findVersion($script, $version);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('view', $script);

        return $this->successResponse([
            'items' => ScriptVersionResearchClaimResource::collection($this->traceabilityService->listMappings($project, $scriptVersion)),
            'traceability' => $this->traceabilityService->summary($scriptVersion),
        ]);
    }

    /**
     * Map a research claim to a script version (human-explicit attachment).
     */
    public function store(ContentProject $project, int $version, ResearchClaim $claim): JsonResponse
    {
        try {
            $script = $this->scriptService->getScriptOrFail($project);
            $scriptVersion = $this->scriptService->findVersion($script, $version);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('update', $script);

        try {
            $mapped = $this->traceabilityService->attachClaim($project, $scriptVersion, $claim);
        } catch (DuplicateScriptResearchClaimException $e) {
            return $this->errorResponse($e->getMessage(), null, 409);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        return $this->successResponse(
            new ScriptVersionResearchClaimResource($mapped),
            'Research claim mapped to script version successfully.',
            201
        );
    }

    /**
     * Remove a research claim from a script version (metadata only).
     */
    public function destroy(ContentProject $project, int $version, ResearchClaim $claim): JsonResponse
    {
        try {
            $script = $this->scriptService->getScriptOrFail($project);
            $scriptVersion = $this->scriptService->findVersion($script, $version);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('update', $script);

        try {
            $this->traceabilityService->detachClaim($project, $scriptVersion, $claim);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        return $this->successResponse(null, 'Research claim detached from script version successfully.');
    }
}
