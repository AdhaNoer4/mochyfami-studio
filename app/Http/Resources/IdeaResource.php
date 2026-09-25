<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IdeaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $priorityLabel = match ($this->priority) {
            3 => 'High',
            2 => 'Medium',
            default => 'Low',
        };

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'color' => $this->category->color ?? '#6366f1',
                ];
            }),
            'hook' => $this->hook,
            'concept' => $this->concept,
            'format' => $this->format->value,
            'format_label' => $this->format->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'project_id' => $this->project?->id,
            'priority' => (int) ($this->priority ?? 1),
            'priority_label' => $priorityLabel,
            'notes' => $this->notes,
            'source_idea' => $this->source_idea,
            'creator_name' => $this->creator?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
