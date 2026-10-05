<?php

namespace App\Http\Resources;

use App\Enums\AssetStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * file_path is intentionally absent. It records where the server keeps a
     * file, and the API has no need to tell a client that. Publishing it would
     * advertise an internal layout, and it would also tempt the frontend into
     * building a URL from it, which is exactly the thing this resource is
     * shaped to make impossible.
     *
     * preview_url, by contrast, is an application route and not a path: it
     * names the endpoint that will stream the file, so a client never has to
     * know where the file is to display it.
     *
     * The technical fields are what the last inspection measured, and
     * metadata_status is what tells a client whether to believe them. A null
     * width with a completed status is an image, which has no width problem;
     * the same null with a failed status is a video nobody could read. The
     * two are only distinguishable because the status is published.
     *
     * allowed_transitions publishes what the state machine permits from the
     * current status, so a client can offer the right actions without keeping
     * its own copy of the graph. It is derived from AssetStatus on every
     * render, never from anything the client sent, and it is a hint about the
     * affordance rather than a promise: the server re-checks the transition,
     * so a stale list can never move an asset somewhere it may not go.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content_project_id' => $this->content_project_id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'allowed_transitions' => collect(AssetStatus::cases())
                ->filter(fn (AssetStatus $target) => $this->status->canTransitionTo($target))
                ->map(fn (AssetStatus $target) => [
                    'status' => $target->value,
                    'label' => $target->label(),
                ])
                ->values(),
            'title' => $this->title,
            'description' => $this->description,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'duration_seconds' => $this->duration_seconds,
            'width' => $this->width,
            'height' => $this->height,
            'aspect_ratio' => $this->aspect_ratio === null ? null : (float) $this->aspect_ratio,
            'metadata_status' => $this->metadata_status?->value,
            'metadata_status_label' => $this->metadata_status?->label(),
            'metadata_error' => $this->metadata_error,
            'metadata_analyzed_at' => $this->metadata_analyzed_at?->toIso8601String(),
            'has_file' => $this->hasFileAttached(),
            'preview_url' => $this->hasFileAttached()
                ? route('assets.preview', [
                    'project' => $this->content_project_id,
                    'asset' => $this->id,
                ])
                : null,
            'source_url' => $this->source_url,
            'source_name' => $this->source_name,
            'license_type' => $this->license_type,
            'attribution' => $this->attribution,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
