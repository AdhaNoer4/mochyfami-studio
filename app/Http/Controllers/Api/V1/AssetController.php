<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssetStatus;
use App\Exceptions\AssetFileStorageException;
use App\Exceptions\InvalidAssetFileTypeException;
use App\Exceptions\InvalidAssetStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\GetAssetsRequest;
use App\Http\Requests\Asset\StoreAssetRequest;
use App\Http\Requests\Asset\UpdateAssetRequest;
use App\Http\Requests\Asset\UpdateAssetStatusRequest;
use App\Http\Requests\Asset\UploadAssetFileRequest;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use App\Models\ContentProject;
use App\Services\AssetFileStorageService;
use App\Services\AssetMetadataService;
use App\Services\AssetService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Throwable;

class AssetController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AssetService $assetService,
        protected AssetFileStorageService $fileStorage,
        protected AssetMetadataService $metadata
    ) {}

    /**
     * List the assets of one project with validated search, filters, sorting,
     * and pagination.
     *
     * Both gates matter here. The policy decides whether this user may read
     * this project at all, and the list itself is read through the project's
     * own relation, so an asset from another project can never appear here
     * even when its id is already known.
     */
    public function index(GetAssetsRequest $request, ContentProject $project): JsonResponse
    {
        Gate::authorize('viewAny', [Asset::class, $project]);

        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 10);

        $assets = $this->assetService->paginateAssets($project, $validated, $perPage);

        return $this->successResponse([
            'items' => AssetResource::collection($assets->items()),
            'pagination' => [
                'total' => $assets->total(),
                'per_page' => $assets->perPage(),
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
            ],
        ]);
    }

    /**
     * Store asset metadata for the project.
     */
    public function store(StoreAssetRequest $request, ContentProject $project): JsonResponse
    {
        Gate::authorize('create', [Asset::class, $project]);

        $asset = $this->assetService->createAsset($project, $request->validated());

        return $this->successResponse(new AssetResource($asset), 'Asset created successfully.', 201);
    }

    /**
     * Show one asset of the project.
     */
    public function show(ContentProject $project, int $asset): JsonResponse
    {
        try {
            $model = $this->assetService->findAsset($project, $asset);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('view', $model);

        return $this->successResponse(new AssetResource($model));
    }

    /**
     * Update asset metadata of the project.
     */
    public function update(UpdateAssetRequest $request, ContentProject $project, int $asset): JsonResponse
    {
        try {
            $model = $this->assetService->findAsset($project, $asset);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('update', $model);

        $updated = $this->assetService->updateAsset($model, $request->validated());

        return $this->successResponse(new AssetResource($updated), 'Asset updated successfully.');
    }

    /**
     * Store a physical file for one of the project's assets.
     *
     * Kept apart from the metadata endpoints on purpose: this is the only
     * route in the asset API that writes to the filesystem, and the only one
     * that ever sets file_path.
     *
     * The order of the checks is the security of this endpoint. The asset is
     * resolved through the project's own relation first, so an id belonging to
     * another project is simply not there and answers 404; only then is the
     * update ability authorized, so a stranger learns nothing about an asset
     * they were never allowed to touch. Validation of the binary has already
     * run by the time the controller is reached.
     */
    public function uploadFile(UploadAssetFileRequest $request, ContentProject $project, int $asset): JsonResponse
    {
        try {
            $model = $this->assetService->findAsset($project, $asset);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('update', $model);

        try {
            $this->fileStorage->assertCompatible($model, $request->file('file'));

            $this->fileStorage->replace($model, $request->file('file'));
        } catch (InvalidAssetFileTypeException $e) {
            return $this->errorResponse($e->getMessage(), ['file' => [$e->getMessage()]], 422);
        } catch (AssetFileStorageException $e) {
            report($e);

            return $this->errorResponse('The file could not be stored.', null, 500);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('The file could not be stored.', null, 500);
        }

        // Measured straight after the write, while the request is already open,
        // because FFprobe reads only container headers and answers in
        // milliseconds for the sizes this studio handles. A queue would leave a
        // fresh upload in an unknown state waiting for a worker, and an
        // inspection that fails here does not fail the upload: it records a
        // failed metadata_status the user can retry with the Analyze action.
        if ((bool) config('assets.metadata.analyse_on_upload', true)) {
            $model = $this->metadata->analyse($model->fresh() ?? $model);
        }

        return $this->successResponse(
            new AssetResource($model->fresh()),
            'File uploaded successfully.',
            200
        );
    }

    /**
     * Measure the attached file again.
     *
     * Technical inspection only. This reads the stored file and writes back
     * width, height, duration, aspect ratio and the measurement status. It
     * cannot change the asset's type or its lifecycle status, attach a
     * requirement, mark fulfillment or approve anything, and it never touches
     * the physical file. A failed measurement is a status the user can see and
     * retry, not an error response: the asset is unchanged either way.
     */
    public function analyze(ContentProject $project, int $asset): JsonResponse
    {
        try {
            $model = $this->assetService->findAsset($project, $asset);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('update', $model);

        return $this->successResponse(
            new AssetResource($this->metadata->analyse($model)),
            'Technical metadata refreshed.',
            200
        );
    }

    /**
     * Move an asset along its lifecycle.
     *
     * Status is separated from the metadata endpoints on purpose. Update
     * deliberately ignores it, so this is the only route that can change where
     * an asset sits in the media pipeline, and the state machine is enforced
     * again in the service rather than being taken from the request.
     *
     * The order of the checks matches the metadata endpoints: the asset is
     * resolved through the project's own relation first, so an id from another
     * project is simply not there and answers 404, then the update ability is
     * authorized before anything is written.
     *
     * A transition the graph does not allow is a 422 and not a 400: the request
     * was well formed, the request itself is wrong for this asset's current
     * state, and the message names both states so a client can correct itself.
     */
    public function transitionStatus(UpdateAssetStatusRequest $request, ContentProject $project, int $asset): JsonResponse
    {
        try {
            $model = $this->assetService->findAsset($project, $asset);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('update', $model);

        try {
            $updated = $this->assetService->transitionStatus(
                $model,
                AssetStatus::from($request->validated()['status'])
            );
        } catch (InvalidAssetStatusTransitionException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Unable to update the asset status.', null, 500);
        }

        return $this->successResponse(
            new AssetResource($updated),
            'Asset status updated successfully.'
        );
    }

    /**
     * Stream the stored file for preview.
     *
     * This is the only way the bytes of a private asset leave the server, and
     * it exists instead of a public URL precisely because the file has to stay
     * private. Three things follow from that, and each is deliberate.
     *
     * The asset is resolved through the project's own relation before anything
     * else, so an id belonging to another project is not there and answers 404,
     * and the policy then decides whether this user may read it at all.
     *
     * The only source of what to serve is the asset row: the endpoint takes no
     * query parameters and no filename, so there is nothing for a caller to
     * redirect at another file, and the Content-Type comes from the stored MIME
     * type detected from the file's contents at upload, never from the request.
     *
     * Only types the upload rules accept are served. That is what keeps an HTML
     * or SVG row from coming back as a document the browser would execute, since
     * both would render inline on this application's own origin.
     */
    public function preview(ContentProject $project, int $asset): BinaryFileResponse|JsonResponse
    {
        try {
            $model = $this->assetService->findAsset($project, $asset);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('view', $model);

        $path = (string) $model->file_path;

        if ($path === '' || ! $this->metadata->isPreviewableMimeType($model->mime_type)) {
            return $this->errorResponse('This asset has no file that can be previewed.', null, 404);
        }

        // The stored path is resolved against the asset directory before it is
        // touched, so a corrupted row cannot aim this endpoint at a file
        // elsewhere on the server.
        if (! $this->metadata->isInsideAssetDirectory($path) || ! $this->fileStorage->disk()->exists($path)) {
            return $this->errorResponse('The stored file for this asset could not be found.', null, 404);
        }

        return response()->file($this->fileStorage->disk()->path($path), [
            'Content-Type' => (string) $model->mime_type,
            'Content-Disposition' => HeaderUtils::DISPOSITION_INLINE.'; filename="'.addslashes((string) $model->file_name).'"',
            // Stops a browser from sniffing the bytes into a type that could be
            // interpreted as a document or a script.
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    /**
     * Delete asset metadata of the project.
     *
     * The stored file goes with it. deleteAsset() removes the row first and
     * then the file, so a failure can leave an unreferenced file behind but can
     * never leave a live row claiming to have a file that is gone.
     */
    public function destroy(ContentProject $project, int $asset): JsonResponse
    {
        try {
            $model = $this->assetService->findAsset($project, $asset);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('delete', $model);

        $this->assetService->deleteAsset($model);

        return $this->successResponse(null, 'Asset deleted successfully.');
    }
}
