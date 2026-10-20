<?php

namespace App\Http\Resources;

use App\Services\TTS\NarrationHasher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ManualDubbingTrackResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Deliberately separate from TtsTrackResource so the automated TTS
     * contract stays byte-for-byte what it was, while this one can carry the
     * review fields a dubbing workflow needs: approval status, uploader and
     * approver display data, and the review timestamps.
     *
     * Nothing here reveals a storage path, a credential, or an unrelated
     * user: the audio is reached only through preview_url, which is the same
     * authenticated asset route the library uses, and the people involved are
     * published as id and name only. is_stale compares this recording's
     * narration fingerprint against the item's current narration, the same
     * way the automated resource does, and is a hint rather than an
     * instruction.
     */
    public function toArray(Request $request): array
    {
        $currentNarration = $this->visualPlanItem?->narration_text;
        $isStale = $currentNarration === null
            ? null
            : NarrationHasher::hash($currentNarration) !== $this->narration_hash;

        return [
            'id' => $this->id,
            'visual_plan_item_id' => $this->visual_plan_item_id,
            'asset_id' => $this->asset_id,
            'source' => $this->source->value,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'approval_status' => $this->approval_status?->value,
            'approval_status_label' => $this->approval_status?->label(),
            'duration_seconds' => $this->duration_seconds,
            'narration_hash' => $this->narration_hash,
            'is_stale' => $isStale,
            'notes' => $this->notes,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'uploader' => $this->whenLoaded('uploader', fn () => $this->uploader === null
                ? null
                : [
                    'id' => $this->uploader->id,
                    'name' => $this->uploader->name,
                ]),
            'approver' => $this->whenLoaded('approver', fn () => $this->approver === null
                ? null
                : [
                    'id' => $this->approver->id,
                    'name' => $this->approver->name,
                ]),
            'preview_url' => $this->whenLoaded('asset', fn () => $this->asset !== null && $this->asset->hasFileAttached()
                ? route('assets.preview', [
                    'project' => $this->asset->content_project_id,
                    'asset' => $this->asset_id,
                ])
                : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
