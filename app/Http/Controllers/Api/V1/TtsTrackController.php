<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InvalidTtsNarrationException;
use App\Exceptions\InvalidTtsTrackStatusTransitionException;
use App\Exceptions\TtsProviderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\TTS\GenerateTtsRequest;
use App\Http\Resources\TtsTrackResource;
use App\Models\Asset;
use App\Models\ContentProject;
use App\Models\VisualPlan;
use App\Services\TTS\TtsGenerationService;
use App\Services\VisualPlanService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

class TtsTrackController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TtsGenerationService $ttsService,
        protected VisualPlanService $visualPlanService
    ) {}

    /**
     * List the synthesis history of one visual plan item.
     *
     * Tracks are returned oldest first, each with is_stale computed from its
     * snapshot hash against the item's current narration. The item's current
     * fingerprint is also published once at the root so a client can tell a
     * list with no tracks from one that simply has nothing new to say.
     */
    public function index(ContentProject $project, int $version, int $item): JsonResponse
    {
        $plan = $this->resolvePlan($project, $version);

        if ($plan === null) {
            return $this->errorResponse('Visual plan not found for this script version.', null, 404);
        }

        Gate::authorize('view', $plan);

        try {
            $tracks = $this->ttsService->listTracks($project, $version, $item);
            $currentNarrationHash = $this->ttsService->currentNarrationHash($project, $version, $item);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        return $this->successResponse([
            'tracks' => TtsTrackResource::collection($tracks),
            'current_narration_hash' => $currentNarrationHash,
        ]);
    }

    /**
     * Synthesize the item's narration into a new track and a stored asset.
     */
    public function store(GenerateTtsRequest $request, ContentProject $project, int $version, int $item): JsonResponse
    {
        $plan = $this->resolvePlan($project, $version);

        if ($plan === null) {
            return $this->errorResponse('Visual plan not found for this script version.', null, 404);
        }

        Gate::authorize('update', $plan);
        $this->authorizeProjectAssets($project, 'create');

        try {
            $track = $this->ttsService->generate($project, $version, $item, $request->validated());

            $track->load(['visualPlanItem:id,narration_text', 'asset']);
        } catch (InvalidTtsNarrationException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        } catch (TtsProviderException $e) {
            return $this->errorResponse('The TTS provider could not complete the request.', null, 502);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Unable to generate the TTS audio.', null, 500);
        }

        return $this->successResponse(
            new TtsTrackResource($track),
            'TTS audio generated successfully.',
            201
        );
    }

    /**
     * Synthesize the item's current narration into a brand new track.
     *
     * The referenced track only has to exist and belong to this item; the new
     * track is created from the current narration exactly like a first
     * generation, and the old track is left untouched.
     */
    public function regenerate(
        GenerateTtsRequest $request,
        ContentProject $project,
        int $version,
        int $item,
        int $track
    ): JsonResponse {
        $plan = $this->resolvePlan($project, $version);

        if ($plan === null) {
            return $this->errorResponse('Visual plan not found for this script version.', null, 404);
        }

        Gate::authorize('update', $plan);
        $this->authorizeProjectAssets($project, 'create');

        try {
            $model = $this->ttsService->regenerate($project, $version, $item, $track, $request->validated());

            $model->load(['visualPlanItem:id,narration_text', 'asset']);
        } catch (InvalidTtsNarrationException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        } catch (TtsProviderException $e) {
            return $this->errorResponse('The TTS provider could not complete the request.', null, 502);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Unable to regenerate the TTS audio.', null, 500);
        }

        return $this->successResponse(
            new TtsTrackResource($model),
            'TTS audio regenerated successfully.',
            201
        );
    }

    /**
     * Archive a track, keeping its asset and its file.
     *
     * This is a delete by name only: the audio stays in the library. Archiving
     * is not reversible through the API, and archiving an already archived
     * track is a 422 rather than a success that changes nothing.
     */
    public function destroy(ContentProject $project, int $version, int $item, int $track): JsonResponse
    {
        $plan = $this->resolvePlan($project, $version);

        if ($plan === null) {
            return $this->errorResponse('Visual plan not found for this script version.', null, 404);
        }

        Gate::authorize('update', $plan);

        try {
            $model = $this->ttsService->archive($project, $version, $item, $track);

            $model->load(['visualPlanItem:id,narration_text', 'asset']);
        } catch (InvalidTtsTrackStatusTransitionException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Unable to archive the TTS track.', null, 500);
        }

        return $this->successResponse(
            new TtsTrackResource($model),
            'TTS track archived successfully.'
        );
    }

    /**
     * Require ownership of the project the generated asset will live in.
     *
     * Mirrors the requirement controller: VisualPlanPolicy lets every
     * authenticated user through, so the plan gate alone cannot answer whether
     * this user owns the project. AssetPolicy resolves ownership, and its one
     * project-scoped write ability is 'create', so the project is authorized
     * through it.
     */
    private function authorizeProjectAssets(ContentProject $project, string $ability): void
    {
        Gate::authorize($ability, [Asset::class, $project]);
    }

    /**
     * Resolve the plan used for authorization, or null when it does not exist.
     */
    private function resolvePlan(ContentProject $project, int $version): ?VisualPlan
    {
        try {
            return $this->visualPlanService->getPlanOrFail($project, $version);
        } catch (ModelNotFoundException) {
            return null;
        }
    }
}
