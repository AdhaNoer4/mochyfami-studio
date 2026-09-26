<?php

namespace App\Http\Resources;

use App\Enums\ScriptStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScriptResource extends JsonResource
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
            'allowed_transitions' => collect(ScriptStatus::cases())
                ->filter(fn (ScriptStatus $target) => $this->status->canTransitionTo($target))
                ->map(fn (ScriptStatus $target) => [
                    'status' => $target->value,
                    'label' => $target->label(),
                    'action' => $this->status->actionLabelFor($target),
                    'destructive' => $target === ScriptStatus::Archived,
                ])
                ->values()
                ->all(),
            'current_version' => new ScriptVersionResource($this->whenLoaded('currentVersion')),
            'version_count' => $this->version_count ?? 0,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
