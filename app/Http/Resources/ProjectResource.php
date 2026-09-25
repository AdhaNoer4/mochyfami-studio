<?php

namespace App\Http\Resources;

use App\Enums\ContentProjectStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'allowed_transitions' => collect(ContentProjectStatus::cases())
                ->filter(fn (ContentProjectStatus $target) => $this->status->canTransitionTo($target))
                ->map(fn (ContentProjectStatus $target) => [
                    'status' => $target->value,
                    'label' => $target->label(),
                    'action' => $this->status->actionLabelFor($target),
                    'destructive' => in_array($target, [ContentProjectStatus::Archived, ContentProjectStatus::Failed], true),
                ])
                ->values()
                ->all(),
            'priority' => (int) ($this->priority ?? 2),
            'content_idea_id' => $this->content_idea_id,
            'idea' => $this->whenLoaded('idea', function () {
                return [
                    'id' => $this->idea->id,
                    'title' => $this->idea->title,
                    'format' => $this->idea->format?->value,
                    'format_label' => $this->idea->format?->label(),
                ];
            }),
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'color' => $this->category->color ?? '#6366f1',
                ];
            }),
            'target_duration_seconds' => (int) ($this->target_duration_seconds ?? 45),
            'language' => $this->language ?? 'en',
            'tone' => $this->tone ?? 'informative',
            'hook' => $this->hook,
            'description' => $this->description,
            'current_step' => $this->current_step ?? 'drafting',
            'progress_percent' => (int) ($this->progress_percent ?? 0),
            'creator_name' => $this->creator?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
