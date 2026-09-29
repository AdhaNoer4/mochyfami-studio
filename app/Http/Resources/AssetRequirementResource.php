<?php

namespace App\Http\Resources;

use App\Enums\AssetRequirementStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetRequirementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'visual_plan_item_id' => $this->visual_plan_item_id,
            'requirement_type' => $this->requirement_type->value,
            'requirement_type_label' => $this->requirement_type->label(),
            'search_query' => $this->search_query,
            'description' => $this->description,
            'target_duration_seconds' => $this->target_duration_seconds,
            'aspect_ratio' => $this->aspect_ratio?->value,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'allowed_transitions' => collect(AssetRequirementStatus::cases())
                ->filter(fn (AssetRequirementStatus $target) => $this->status->canTransitionTo($target))
                ->map(fn (AssetRequirementStatus $target) => [
                    'status' => $target->value,
                    'label' => $target->label(),
                    'action' => $this->status->actionLabelFor($target),
                    'destructive' => $target === AssetRequirementStatus::Skipped,
                ])
                ->values()
                ->all(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
