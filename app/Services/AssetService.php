<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\ContentProject;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AssetService
{
    /**
     * List the assets of one project.
     *
     * The list is always filtered through the project's own relation, so an
     * asset from another project can never appear here even if its id is known.
     */
    public function listAssets(ContentProject $project): Collection
    {
        return $project->assets()->latest('id')->get();
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
     * Delete asset metadata.
     *
     * Deleting a row only removes metadata. Part 1 stores no files, so there
     * is nothing on disk to clean up and no storage driver to call.
     *
     * The asset must come from findAsset(), for the same reason as in
     * updateAsset().
     */
    public function deleteAsset(Asset $asset): void
    {
        $asset->delete();
    }
}
