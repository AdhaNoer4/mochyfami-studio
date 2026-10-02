<?php

namespace App\Http\Resources;

use App\Models\VisualPlan;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetFulfillmentQualityResource extends JsonResource
{
    public function __construct(
        protected VisualPlan $visualPlan,
        protected array $quality
    ) {
        parent::__construct($visualPlan);
    }

    /**
     * Transform the asset fulfillment readiness evaluation into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'visual_plan_id' => $this->visualPlan->id,
            'script_version_id' => $this->visualPlan->script_version_id,
            'status' => $this->visualPlan->getRawOriginal('status'),
            'ready' => $this->quality['ready'],
            'score' => $this->quality['score'],
            'summary' => $this->quality['summary'],
            'items' => $this->quality['items'],
            'blockers' => $this->quality['blockers'],
            'warnings' => $this->quality['warnings'],
            'issues' => $this->quality['issues'],
        ];
    }
}
