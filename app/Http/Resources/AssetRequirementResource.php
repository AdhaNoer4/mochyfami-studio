<?php

namespace App\Http\Resources;

use App\Enums\AssetRequirementStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetRequirementResource extends JsonResource
{
    /**
     * Transform the requirement into an array.
     *
     * selected_asset_id is published as an id rather than a nested asset because
     * the requirement list does not load the asset behind it. A client that needs
     * the detail has the candidate list, which carries the asset in full.
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
            'selected_asset_id' => $this->selected_asset_id,
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
