<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ScriptStatus;
use App\Exceptions\DuplicateScriptException;
use App\Exceptions\InvalidScriptStatusTransitionException;
use App\Exceptions\ScriptNotReadyForCreationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Script\StoreScriptRequest;
use App\Http\Requests\Script\TransitionScriptStatusRequest;
use App\Http\Resources\ScriptResource;
use App\Models\ContentProject;
use App\Models\Script;
use App\Services\ScriptService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ScriptController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ScriptService $scriptService
    ) {}

    /**
     * Create the project's script aggregate with its initial version.
     */
    public function store(StoreScriptRequest $request, ContentProject $project): JsonResponse
    {
        Gate::authorize('create', Script::class);

        try {
            $script = $this->scriptService->createScript($project, $request->validated());
        } catch (DuplicateScriptException $e) {
            return $this->errorResponse($e->getMessage(), null, 409);
        } catch (ScriptNotReadyForCreationException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        }

        return $this->successResponse(new ScriptResource($script), 'Script created successfully.', 201);
    }

    /**
     * Display the project script with its current version.
     */
    public function show(ContentProject $project): JsonResponse
    {
        $script = $this->scriptService->getScript($project);

        if (! $script) {
            return $this->successResponse(null, 'No script yet for this project.');
        }

        return $this->successResponse(new ScriptResource($script));
    }

    /**
     * Transition the script status.
     */
    public function transitionStatus(TransitionScriptStatusRequest $request, ContentProject $project): JsonResponse
    {
        try {
            $script = $this->scriptService->getScriptOrFail($project);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('updateStatus', $script);

        try {
            $updatedScript = $this->scriptService->transitionStatus(
                $project,
                ScriptStatus::from($request->validated()['status'])
            );
        } catch (InvalidScriptStatusTransitionException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Unable to update script status.', null, 500);
        }

        return $this->successResponse(new ScriptResource($updatedScript), 'Script status updated successfully.');
    }
}
