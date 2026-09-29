<?php

namespace App\Http\Resources;

use App\Enums\VisualPlanStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisualPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'content_project_id' => $this->content_project_id,
            'script_version_id' => $this->script_version_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'allowed_transitions' => collect(VisualPlanStatus::cases())
                ->filter(fn (VisualPlanStatus $target) => $this->status->canTransitionTo($target))
                ->map(fn (VisualPlanStatus $target) => [
                    'status' => $target->value,
                    'label' => $target->label(),
                    'action' => $this->status->actionLabelFor($target),
                    'destructive' => $target === VisualPlanStatus::Archived,
                ])
                ->values()
                ->all(),
            'title' => $this->title,
            'notes' => $this->notes,
            'items' => VisualPlanItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
