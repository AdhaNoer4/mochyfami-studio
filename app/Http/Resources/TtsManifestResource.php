<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The TTS production manifest for one script version.
 *
 * The resource only shapes the projection produced by TtsManifestService,
 * keeping the API contract explicit while the service stays the single owner
 * of the manifest rules.
 */
class TtsManifestResource extends JsonResource
{
    /**
     * @return array{ready: bool, script_version_id: int, visual_plan_id: int|null, items: array<int, mixed>, blockers: array<int, array<string, mixed>>, warnings: array<int, array<string, mixed>>, info: array<string, mixed>}
     */
    public function toArray(Request $request): array
    {
        return [
            'ready' => $this->resource['ready'],
            'script_version_id' => $this->resource['script_version_id'],
            'visual_plan_id' => $this->resource['visual_plan_id'],
            'items' => $this->resource['items'],
            'blockers' => $this->resource['blockers'],
            'warnings' => $this->resource['warnings'],
            'info' => $this->resource['info'],
        ];
    }
}
