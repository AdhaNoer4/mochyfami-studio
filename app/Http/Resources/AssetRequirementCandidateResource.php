<?php

namespace App\Http\Resources;

use App\Models\Asset;
use App\Models\AssetRequirement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One candidate, with the answer to "could this satisfy the requirement".
 *
 * The asset fields are flattened rather than nested under an asset key,
 * because a candidate list is read as a list of assets with a decision
 * attached, and one level of indirection would make every caller unwrap it.
 *
 * compatible and selected answer different questions and both are published.
 * An asset can be selected and incompatible, which is the normal state of a
 * requirement the user is still working on: they have pointed at something and
 * the checks disagree. Hiding one behind the other would hide the disagreement.
 */
class AssetRequirementCandidateResource extends JsonResource
{
    /**
     * @param  Asset  $asset  the candidate being reported on
     * @param  array{compatible: bool, issues: array<int, array{code: string, severity: string, message: string, field: string}>}  $compatibility
     */
    public function __construct(
        protected AssetRequirement $requirement,
        Asset $asset,
        protected array $compatibility
    ) {
        parent::__construct($asset);
    }

    /**
     * Transform the candidate into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Asset $asset */
        $asset = $this->resource;

        return [
            'id' => $asset->id,
            'content_project_id' => $asset->content_project_id,
            'type' => $asset->type->value,
            'type_label' => $asset->type->label(),
            'status' => $asset->status->value,
            'status_label' => $asset->status->label(),
            'title' => $asset->title,
            'description' => $asset->description,
            'file_name' => $asset->file_name,
            'mime_type' => $asset->mime_type,
            'file_size' => $asset->file_size,
            'duration_seconds' => $asset->duration_seconds,
            'width' => $asset->width,
            'height' => $asset->height,
            'aspect_ratio' => $asset->aspect_ratio === null ? null : (float) $asset->aspect_ratio,
            'metadata_status' => $asset->metadata_status?->value,
            'metadata_status_label' => $asset->metadata_status?->label(),
            'metadata_error' => $asset->metadata_error,
            'has_file' => $asset->hasFileAttached(),
            'preview_url' => $asset->hasFileAttached()
                ? route('assets.preview', [
                    'project' => $asset->content_project_id,
                    'asset' => $asset->id,
                ])
                : null,
            'source_url' => $asset->source_url,
            'source_name' => $asset->source_name,
            'license_type' => $asset->license_type,
            'attribution' => $asset->attribution,
            'selected' => $this->requirement->selected_asset_id === $asset->id,
            'compatible' => $this->compatibility['compatible'],
            'issues' => $this->compatibility['issues'],
        ];
    }
}
