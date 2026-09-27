<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ResearchGenerationProviderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Research\GenerateResearchRequest;
use App\Http\Resources\ResearchClaimResource;
use App\Http\Resources\ResearchResource;
use App\Http\Resources\ResearchSourceResource;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Services\ResearchGenerationService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ResearchGenerationController extends Controller
{
    use ApiResponse;

    public const HUMAN_VERIFICATION_NOTICE = 'AI-generated research is a starting point and must be verified against reliable sources before being treated as factual evidence.';

    public function __construct(
        protected ResearchGenerationService $researchGenerationService
    ) {}

    /**
     * Generate research candidates for the project using the configured
     * provider. Candidates are persisted as unverified claims/sources and
     * never treated as verified evidence.
     */
    public function generate(GenerateResearchRequest $request, ContentProject $project): JsonResponse
    {
        Gate::authorize('create', ResearchClaim::class);

        try {
            $result = $this->researchGenerationService->generate($project, $request->validated());
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        } catch (ResearchGenerationProviderException $e) {
            if ($e->getCode() === ResearchGenerationProviderException::UNKNOWN_PROVIDER) {
                return $this->errorResponse($e->getMessage(), null, 422);
            }

            return $this->errorResponse('The research generation provider could not complete the request.', null, 502);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Unable to generate research candidates.', null, 500);
        }

        return $this->successResponse([
            'report' => new ResearchResource($result['report']),
            'summary' => $result['summary'],
            'generated_claims' => ResearchClaimResource::collection($result['generated_claims']),
            'generated_sources' => ResearchSourceResource::collection($result['generated_sources']),
            'generation' => $result['generation'],
            'quality' => $result['quality'],
            'warning' => self::HUMAN_VERIFICATION_NOTICE,
        ], 'Research candidates generated successfully.', 201);
    }
}
