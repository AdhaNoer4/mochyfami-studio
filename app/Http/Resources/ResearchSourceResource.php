<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResearchSourceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'research_report_id' => $this->research_report_id,
            'title' => $this->title,
            'url' => $this->url,
            'domain' => $this->domain,
            'source_type' => $this->source_type->value,
            'source_type_label' => $this->source_type->label(),
            'claims' => ResearchClaimResource::collection($this->whenLoaded('claims')),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
