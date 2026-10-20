<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InvalidAssetFileTypeException;
use App\Exceptions\InvalidManualApprovalStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManualDubbing\UploadManualDubbingRequest;
use App\Http\Resources\ManualDubbingTrackResource;
use App\Models\Asset;
use App\Models\ContentProject;
use App\Models\TtsTrack;
use App\Models\VisualPlan;
use App\Services\TTS\ManualDubbingService;
use App\Services\VisualPlanService;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ManualDubbingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ManualDubbingService $manualDubbingService,
        protected VisualPlanService $visualPlanService
    ) {}

    /**
     * List the manual dubbing history of one visual plan item, newest first.
     *
     * Manual history is kept apart from automated TTS history on purpose: the
     * two answer different questions, and mixing them in one list is exactly
     * what Part 9's source isolation was introduced to prevent.
     */
    public function index(ContentProject $project, int $version, int $item): JsonResponse
    {
        $plan = $this->resolvePlan($project, $version);

        if ($plan === null) {
            return $this->errorResponse('Visual plan not found for this script version.', null, 404);
        }

        $this->authorizeProjectAssets($project, 'viewAny');

        try {
            $tracks = $this->manualDubbingService->listManualTracks($project, $version, $item);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        return $this->successResponse([
            'tracks' => ManualDubbingTrackResource::collection($tracks),
        ]);
    }

    /**
     * Upload a manual recording as a new track on the item.
     *
     * The order of the checks mirrors the asset library's file upload: the
     * plan is resolved first (404), then project ownership is authorized
     * through AssetPolicy (403), and only then does the service touch the
     * database or the filesystem.
     */
    public function store(UploadManualDubbingRequest $request, ContentProject $project, int $version, int $item): JsonResponse
    {
        $plan = $this->resolvePlan($project, $version);

        if ($plan === null) {
            return $this->errorResponse('Visual plan not found for this script version.', null, 404);
        }

        $this->authorizeProjectAssets($project, 'create');

        try {
            $track = $this->manualDubbingService->upload(
                $project,
                $version,
                $item,
                $request->file('file'),
                $request->validated()['notes'] ?? null,
                $request->user()
            );
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        } catch (InvalidAssetFileTypeException $e) {
            return $this->errorResponse($e->getMessage(), ['file' => [$e->getMessage()]], 422);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Unable to store the manual dubbing audio.', null, 500);
        }

        $track->load(['visualPlanItem:id,narration_text', 'asset', 'uploader:id,name', 'approver:id,name']);

        return $this->successResponse(
            new ManualDubbingTrackResource($track),
            'Manual dubbing audio uploaded successfully.',
            201
        );
    }

    /**
     * Submit a draft recording for review.
     */
    public function submit(ContentProject $project, int $version, int $item, int $track): JsonResponse
    {
        return $this->applyApprovalAction(
            $project,
            $version,
            $item,
            $track,
            fn (ContentProject $project, int $version, int $item, int $track) => $this->manualDubbingService->submit($project, $version, $item, $track),
            'Manual dubbing submitted for review.',
            'Unable to submit the manual dubbing track.'
        );
    }

    /**
     * Approve a submitted recording. Approval provenance is stamped
     * server-side by the service; the request body carries no fields.
     */
    public function approve(Request $request, ContentProject $project, int $version, int $item, int $track): JsonResponse
    {
        $approver = $request->user();

        return $this->applyApprovalAction(
            $project,
            $version,
            $item,
            $track,
            fn (ContentProject $project, int $version, int $item, int $track) => $this->manualDubbingService->approve($project, $version, $item, $track, $approver),
            'Manual dubbing approved.',
            'Unable to approve the manual dubbing track.'
        );
    }

    /**
     * Reject a submitted recording. Like approval, the request body carries
     * no fields and the service owns every write.
     */
    public function reject(ContentProject $project, int $version, int $item, int $track): JsonResponse
    {
        return $this->applyApprovalAction(
            $project,
            $version,
            $item,
            $track,
            fn (ContentProject $project, int $version, int $item, int $track) => $this->manualDubbingService->reject($project, $version, $item, $track),
            'Manual dubbing rejected.',
            'Unable to reject the manual dubbing track.'
        );
    }

    /**
     * Shared scaffolding for the three approval actions: resolve the plan,
     * authorize project ownership, run the service action, and map its
     * failures. Approval is a write into the project's dubbing history, so it
     * is authorized through AssetPolicy's project-scoped write ability the
     * same way an asset write is.
     *
     * @param  Closure(ContentProject, int, int, int): TtsTrack  $action
     */
    private function applyApprovalAction(
        ContentProject $project,
        int $version,
        int $item,
        int $track,
        Closure $action,
        string $successMessage,
        string $failureMessage
    ): JsonResponse {
        $plan = $this->resolvePlan($project, $version);

        if ($plan === null) {
            return $this->errorResponse('Visual plan not found for this script version.', null, 404);
        }

        $this->authorizeProjectAssets($project, 'create');

        try {
            $model = $action($project, $version, $item, $track);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        } catch (InvalidManualApprovalStatusTransitionException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse($failureMessage, null, 500);
        }

        $model->load(['visualPlanItem:id,narration_text', 'asset', 'uploader:id,name', 'approver:id,name']);

        return $this->successResponse(new ManualDubbingTrackResource($model), $successMessage);
    }

    /**
     * Require ownership of the project this dubbing history lives in.
     *
     * Mirrors TtsTrackController: VisualPlanPolicy lets every authenticated
     * user through, so the plan gate alone cannot answer whether this user
     * owns the project. AssetPolicy resolves ownership, and its project-
     * scoped abilities are the ones used here — viewAny for reading the
     * history, create for the upload and the approval writes.
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
