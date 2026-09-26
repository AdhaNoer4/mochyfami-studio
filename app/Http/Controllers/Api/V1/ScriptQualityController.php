<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScriptQualityResource;
use App\Models\ContentProject;
use App\Models\Script;
use App\Services\Script\ScriptQualityService;
use App\Services\ScriptService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ScriptQualityController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ScriptService $scriptService,
        protected ScriptQualityService $qualityService
    ) {}

    /**
     * Evaluate the deterministic script quality / research alignment gate.
     *
     * Read-only: the evaluation never mutates data and never calls AI or
     * external services.
     */
    public function show(ContentProject $project): JsonResponse
    {
        try {
            $script = $this->scriptService->getScriptOrFail($project);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('view', $script);

        $result = $this->qualityService->evaluateScript($script);

        return $this->successResponse(new ScriptQualityResource($script, $result));
    }
}
