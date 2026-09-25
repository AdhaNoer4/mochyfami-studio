<?php

namespace App\Http\Resources;

use App\Enums\ResearchStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResearchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->content_project_id,
            'project' => $this->whenLoaded('project', function () {
                return [
                    'id' => $this->project->id,
                    'title' => $this->project->title,
                ];
            }),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'allowed_transitions' => collect(ResearchStatus::cases())
                ->filter(fn (ResearchStatus $target) => $this->status->canTransitionTo($target))
                ->map(fn (ResearchStatus $target) => [
                    'status' => $target->value,
                    'label' => $target->label(),
                    'action' => $this->status->actionLabelFor($target),
                    'destructive' => $target === ResearchStatus::Failed,
                ])
                ->values()
                ->all(),
            'summary' => $this->summary,
            'researched_at' => $this->researched_at?->toIso8601String(),
            'claims' => ResearchClaimResource::collection($this->whenLoaded('claims')),
            'sources' => ResearchSourceResource::collection($this->whenLoaded('sources')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
