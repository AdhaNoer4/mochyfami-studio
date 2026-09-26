<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScriptVersionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'script_id' => $this->script_id,
            'version' => $this->version,
            'title' => $this->title,
            'hook' => $this->hook,
            'body' => $this->body,
            'closing' => $this->closing,
            'duration_seconds' => $this->duration_seconds,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
