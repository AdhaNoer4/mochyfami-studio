<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The TTS readiness report for one script version.
 *
 * The resource only shapes the projection produced by TtsReadinessService,
 * keeping the API contract explicit while the service stays the single owner
 * of the readiness rules.
 */
class TtsReadinessResource extends JsonResource
{
    /**
     * @return array{ready: bool, blockers: array<int, array<string, mixed>>, warnings: array<int, array<string, mixed>>, info: array<string, mixed>}
     */
    public function toArray(Request $request): array
    {
        return [
            'ready' => $this->resource['ready'],
            'blockers' => $this->resource['blockers'],
            'warnings' => $this->resource['warnings'],
            'info' => $this->resource['info'],
        ];
    }
}
