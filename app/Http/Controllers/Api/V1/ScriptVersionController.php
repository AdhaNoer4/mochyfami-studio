<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Script\ReviseScriptVersionRequest;
use App\Http\Requests\Script\StoreScriptVersionRequest;
use App\Http\Requests\Script\UpdateCurrentScriptVersionRequest;
use App\Http\Resources\ScriptQualityResource;
use App\Http\Resources\ScriptResource;
use App\Http\Resources\ScriptVersionResource;
use App\Models\ContentProject;
use App\Services\Script\ScriptQualityService;
use App\Services\Script\ScriptResearchTraceabilityService;
use App\Services\ScriptService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ScriptVersionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ScriptService $scriptService,
        protected ScriptQualityService $qualityService,
        protected ScriptResearchTraceabilityService $traceabilityService,
    ) {}

    /**
     * List the script versions ordered deterministically by version number.
     */
    public function index(ContentProject $project): JsonResponse
    {
        try {
            $script = $this->scriptService->getScriptOrFail($project);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('view', $script);

        return $this->successResponse(
            ScriptVersionResource::collection($this->scriptService->listVersions($script))
        );
    }

    /**
     * Append the next script version and make it the current one.
     */
    public function store(StoreScriptVersionRequest $request, ContentProject $project): JsonResponse
    {
        try {
            $script = $this->scriptService->getScriptOrFail($project);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('update', $script);

        $updatedScript = $this->scriptService->createVersion($project, $request->validated());

        return $this->successResponse(
            new ScriptResource($updatedScript),
            'Script version created successfully.',
            201
        );
    }

    /**
     * Display a specific script version by its version number (read-only).
     */
    public function show(ContentProject $project, int $version): JsonResponse
    {
        try {
            $script = $this->scriptService->getScriptOrFail($project);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('view', $script);

        try {
            $scriptVersion = $this->scriptService->findVersion($script, $version);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        return $this->successResponse(new ScriptVersionResource($scriptVersion));
    }

    /**
     * Edit the current version, producing a new immutable version.
     */
    public function updateCurrent(UpdateCurrentScriptVersionRequest $request, ContentProject $project): JsonResponse
    {
        try {
            $script = $this->scriptService->getScriptOrFail($project);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('update', $script);

        try {
            $updatedScript = $this->scriptService->updateCurrentVersion($project, $request->validated());
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        return $this->successResponse(
            new ScriptResource($updatedScript),
            'Script current version updated successfully.'
        );
    }

    /**
     * Revise a script version, producing a NEW immutable version.
     *
     * The source version may be current or historical/archived and is never
     * mutated. Only edited fields (hook/body/closing) are replaced; the rest
     * are inherited. The new version becomes current, the script is forced
     * back to draft, and research-claim mappings are never copied.
     */
    public function revise(ReviseScriptVersionRequest $request, ContentProject $project, int $version): JsonResponse
    {
        try {
            $script = $this->scriptService->getScriptOrFail($project);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('update', $script);

        try {
            $updatedScript = $this->scriptService->createRevision($project, $version, $request->validated());
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        $newVersion = $updatedScript->currentVersion;

        return $this->successResponse([
            'script' => new ScriptResource($updatedScript),
            'version' => new ScriptVersionResource($newVersion),
            'quality' => new ScriptQualityResource($updatedScript, $this->qualityService->evaluateScript($updatedScript)),
            'traceability' => $this->traceabilityService->summary($newVersion),
        ], 'Script version revised successfully.', 201);
    }
}
