<?php

namespace App\Http\Resources;

use App\Models\VisualPlan;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetProductionManifestResource extends JsonResource
{
    public function __construct(
        protected VisualPlan $visualPlan,
        protected array $manifest
    ) {
        parent::__construct($visualPlan);
    }

    /**
     * Transform the production asset manifest into an array.
     *
     * The status is read raw rather than through the model cast, so a corrupt
     * stored value is reported by the manifest rather than thrown by the
     * serializer on its way out. The entries themselves carry no file_path and
     * no URL: production resolves the file from the asset id.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'visual_plan_id' => $this->visualPlan->id,
            'script_version_id' => $this->visualPlan->script_version_id,
            'status' => $this->visualPlan->getRawOriginal('status'),
            'ready' => $this->manifest['ready'],
            'summary' => $this->manifest['summary'],
            'entries' => $this->manifest['entries'],
            'issues' => $this->manifest['issues'],
        ];
    }
}
