<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisualPlanItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'visual_plan_id' => $this->visual_plan_id,
            'order' => $this->order,
            'section' => $this->section->value,
            'section_label' => $this->section->label(),
            'narration_text' => $this->narration_text,
            'visual_type' => $this->visual_type->value,
            'visual_type_label' => $this->visual_type->label(),
            'visual_prompt' => $this->visual_prompt,
            'duration_seconds' => $this->duration_seconds,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
