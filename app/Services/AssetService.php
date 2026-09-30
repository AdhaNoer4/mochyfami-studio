<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\ContentProject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AssetService
{
    /**
     * @param  AssetFileStorageService  $files  The only path to the filesystem.
     *                                          Injected so that deleting an
     *                                          asset also removes its file
     *                                          without this class reaching
     *                                          for Storage directly.
     */
    public function __construct(
        protected AssetFileStorageService $files
    ) {}

    /**
     * Columns a caller is allowed to order by.
     *
     * This is a second line of defence, not the first: GetAssetsRequest rejects
     * anything outside this list with a 422. The check is repeated here so
     * that a future caller of this service cannot smuggle an arbitrary column
     * name into an ORDER BY by skipping the request class.
     */
    protected array $allowedSortFields = [
        'created_at',
        'updated_at',
        'title',
        'file_size',
        'duration_seconds',
    ];

    /**
     * Paginated asset list with validated search, filters, and sorting.
     *
     * The query is started from the project's own relation rather than from
     * the Asset model with a where added afterwards. That ordering matters:
     * the project constraint is part of the query's construction, so no
     * combination of search, filter, sort, or page can widen it. An asset
     * from another project is unreachable from here, not filtered out.
     *
     * Search, filtering, ordering, and the row limit all happen in SQL. Nothing
     * is loaded and then narrowed in PHP, and no relationship is eager loaded:
     * an asset is a flat metadata row and eager loading the project it already
     * belongs to would only add a query per row.
     */
    public function paginateAssets(ContentProject $project, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = $project->assets()->getQuery();

        // Database bound search across the fields a human recognises an asset
        // by. The value is passed as a bound parameter by Eloquent, so a
        // search term containing quotes or wildcards cannot alter the query.
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('file_name', 'like', "%{$search}%")
                    ->orWhere('source_name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Strict allowlist verification. The comparison is strict so a numeric
        // or nullish sort value can never be coerced into a match.
        $sortField = $filters['sort'] ?? 'created_at';
        if (! in_array($sortField, $this->allowedSortFields, true)) {
            $sortField = 'created_at';
        }

        $sortDirection = strtolower($filters['direction'] ?? 'desc');
        if (! in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'desc';
        }

        $query->orderBy($sortField, $sortDirection);

        // A secondary order keeps pagination stable. Without it two assets
        // that tie on the requested column can swap between pages, so a row
        // the caller already saw reappears on the next page and another never
        // appears at all.
        if ($sortField !== 'id') {
            $query->orderBy('id', 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Find one asset within project scope.
     *
     * @throws ModelNotFoundException
     */
    public function findAsset(ContentProject $project, int $assetId): Asset
    {
        $asset = $project->assets()->whereKey($assetId)->first();

        if (! $asset) {
            throw new ModelNotFoundException('Asset not found for this project.');
        }

        return $asset;
    }

    /**
     * Create asset metadata for a project.
     *
     * The status is always pending: it describes where the asset is in the
     * media pipeline, not user intent, so it is decided server side and never
     * accepted from the client. Only metadata is stored. No file is written,
     * no source url is fetched, and no provider is contacted.
     *
     * file_path is never read from the request. A path is a server side fact
     * about where a file lives, so it stays under server control and is
     * written only by the part that actually stores a file.
     */
    public function createAsset(ContentProject $project, array $data): Asset
    {
        return $project->assets()->create([
            'type' => $data['type'],
            'status' => AssetStatus::Pending,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'file_name' => $data['file_name'] ?? null,
            'mime_type' => $data['mime_type'] ?? null,
            'file_size' => $data['file_size'] ?? null,
            'duration_seconds' => $data['duration_seconds'] ?? null,
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'source_url' => $data['source_url'] ?? null,
            'source_name' => $data['source_name'] ?? null,
            'license_type' => $data['license_type'] ?? null,
            'attribution' => $data['attribution'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Update asset metadata without ever touching its status. Moving an asset
     * along its lifecycle is a separate concern and has no endpoint yet.
     *
     * The asset is passed in already resolved through findAsset(), which is
     * what makes it safe to mutate here: the scoping check has already proved
     * this row belongs to the requested project.
     *
     * file_path is deliberately absent here for the same reason it is absent
     * on create: no client input reaches a filesystem path.
     */
    public function updateAsset(Asset $asset, array $data): Asset
    {
        $asset->fill([
            'type' => $data['type'] ?? $asset->type->value,
            'title' => array_key_exists('title', $data) ? $data['title'] : $asset->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $asset->description,
            'file_name' => array_key_exists('file_name', $data) ? $data['file_name'] : $asset->file_name,
            'mime_type' => array_key_exists('mime_type', $data) ? $data['mime_type'] : $asset->mime_type,
            'file_size' => array_key_exists('file_size', $data) ? $data['file_size'] : $asset->file_size,
            'duration_seconds' => array_key_exists('duration_seconds', $data) ? $data['duration_seconds'] : $asset->duration_seconds,
            'width' => array_key_exists('width', $data) ? $data['width'] : $asset->width,
            'height' => array_key_exists('height', $data) ? $data['height'] : $asset->height,
            'source_url' => array_key_exists('source_url', $data) ? $data['source_url'] : $asset->source_url,
            'source_name' => array_key_exists('source_name', $data) ? $data['source_name'] : $asset->source_name,
            'license_type' => array_key_exists('license_type', $data) ? $data['license_type'] : $asset->license_type,
            'attribution' => array_key_exists('attribution', $data) ? $data['attribution'] : $asset->attribution,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $asset->notes,
        ])->save();

        return $asset->fresh();
    }

    /**
     * Delete asset metadata and the file stored behind it.
     *
     * The row goes first, on purpose. Deleting in the other order would mean a
     * failed row delete leaves a live row pointing at a file that has already
     * been removed, and that row would then claim to have a file it does not
     * have. Deleting the row first means the worst case is the reverse: a file
     * left on disk that nothing points at, which is reported and can be swept
     * up later. An asset without a file is the normal case, not an error, so
     * it deletes without touching storage at all.
     *
     * The asset must come from findAsset(), for the same reason as in
     * updateAsset().
     */
    public function deleteAsset(Asset $asset): void
    {
        $filePath = $asset->file_path;

        $asset->delete();

        $this->files->deleteQuietly($filePath);
    }
}
