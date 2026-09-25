<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResearchClaimResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'research_report_id' => $this->research_report_id,
            'claim' => $this->claim,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'importance' => $this->importance->value,
            'importance_label' => $this->importance->label(),
            'sources' => ResearchSourceResource::collection($this->whenLoaded('sources')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
