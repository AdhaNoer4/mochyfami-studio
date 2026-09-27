<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ScriptGenerationNotReadyException;
use App\Exceptions\ScriptGenerationProviderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Script\GenerateScriptRequest;
use App\Http\Resources\ScriptQualityResource;
use App\Http\Resources\ScriptResource;
use App\Http\Resources\ScriptVersionResource;
use App\Models\ContentProject;
use App\Models\Script;
use App\Services\Script\ScriptGenerationService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ScriptGenerationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ScriptGenerationService $generationService
    ) {}

    /**
     * Generate a new script version using the configured AI provider.
     *
     * The generated content becomes a NEW version; the previous version and
     * the script status are never modified. Output is a draft that requires
     * human review.
     */
    public function generate(GenerateScriptRequest $request, ContentProject $project): JsonResponse
    {
        Gate::authorize('create', Script::class);

        try {
            $result = $this->generationService->generate($project, $request->validated());
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        } catch (ScriptGenerationNotReadyException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (ScriptGenerationProviderException $e) {
            if ($e->getCode() === ScriptGenerationProviderException::UNKNOWN_PROVIDER) {
                return $this->errorResponse($e->getMessage(), null, 422);
            }

            return $this->errorResponse('The AI provider could not complete the request.', null, 502);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Unable to generate the script.', null, 500);
        }

        return $this->successResponse([
            'script' => new ScriptResource($result['script']),
            'generated_version' => new ScriptVersionResource($result['generated_version']),
            'generation' => $result['generation'],
            'quality' => new ScriptQualityResource($result['script'], $result['quality']),
        ], 'Script generated successfully.', 201);
    }
}
