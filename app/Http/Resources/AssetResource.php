<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * file_path is intentionally absent. It records where the server keeps a
     * file, and the API has no need to tell a client that. Nothing in this
     * part writes or reads a file, so publishing a path would advertise an
     * internal layout that does not exist yet.
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
            'title' => $this->title,
            'description' => $this->description,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'duration_seconds' => $this->duration_seconds,
            'width' => $this->width,
            'height' => $this->height,
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
