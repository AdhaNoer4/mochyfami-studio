<?php

namespace App\Http\Resources;

use App\Services\TTS\NarrationHasher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TtsTrackResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * narration_snapshot records the exact narration this track was spoken
     * from, and narration_hash is its fingerprint. Neither reveals a storage
     * path: the audio behind a completed track is reached only through
     * preview_url, which is the same authenticated asset route the library
     * uses, so a track can never hand a client an internal server path.
     *
     * is_stale compares the hash against the item's current narration. It is a
     * hint to the client that regenerating would speak something different,
     * never an instruction: only the regeneration endpoint can decide whether
     * a new track is warranted. An item that is not loaded (a track viewed out
     * of its plan) has no current narration to compare, so is_stale is null.
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
            'provider' => $this->provider,
            'model' => $this->model,
            'voice' => $this->voice,
            'language' => $this->language,
            'speed' => $this->speed === null ? null : (float) $this->speed,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'duration_seconds' => $this->duration_seconds,
            'narration_snapshot' => $this->narration_snapshot,
            'narration_hash' => $this->narration_hash,
            'is_stale' => $isStale,
            'error' => $this->error,
            'preview_url' => $this->whenLoaded('asset', fn () => $this->asset->hasFileAttached()
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
