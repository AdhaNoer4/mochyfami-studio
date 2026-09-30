<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\GetAssetsRequest;
use App\Http\Requests\Asset\StoreAssetRequest;
use App\Http\Requests\Asset\UpdateAssetRequest;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use App\Models\ContentProject;
use App\Services\AssetService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AssetController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AssetService $assetService
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
     * Delete asset metadata of the project.
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
