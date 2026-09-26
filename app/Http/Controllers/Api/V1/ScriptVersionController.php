<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Script\StoreScriptVersionRequest;
use App\Http\Requests\Script\UpdateCurrentScriptVersionRequest;
use App\Http\Resources\ScriptResource;
use App\Http\Resources\ScriptVersionResource;
use App\Models\ContentProject;
use App\Services\ScriptService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ScriptVersionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ScriptService $scriptService
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
}
