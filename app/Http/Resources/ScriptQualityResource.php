<?php

namespace App\Http\Resources;

use App\Models\Script;
use Illuminate\Http\Resources\Json\JsonResource;

class ScriptQualityResource extends JsonResource
{
    public function __construct(
        protected Script $script,
        protected array $quality
    ) {
        parent::__construct($script);
    }

    /**
     * Transform the script quality evaluation into an array.
     */
    public function toArray($request): array
    {
        $currentVersion = $this->script->currentVersion;

        return [
            'script_id' => $this->script->id,
            'version_id' => $currentVersion?->id ?? null,
            'version' => $currentVersion?->version ?? null,
            'status' => $this->script->status->value,
            'ready' => $this->quality['ready'],
            'score' => $this->quality['score'],
            'summary' => $this->quality['summary'],
            'blockers' => $this->quality['blockers'],
            'warnings' => $this->quality['warnings'],
            'checks' => $this->quality['checks'],
            'claim_alignment' => $this->quality['claim_alignment'],
        ];
    }
}
