<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScriptVersionResearchClaimResource extends JsonResource
{
    /**
     * Transform a mapped research claim into its traceability payload.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'claim' => $this->claim,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'importance' => $this->importance->value,
            'importance_label' => $this->importance->label(),
            'has_evidence' => $this->whenLoaded('sources', fn () => $this->sources->isNotEmpty(), false),
            'sources' => ResearchSourceResource::collection($this->whenLoaded('sources')),
        ];
    }
}
