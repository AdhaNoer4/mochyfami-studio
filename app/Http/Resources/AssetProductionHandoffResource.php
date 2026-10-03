<?php

namespace App\Http\Resources;

use App\Models\VisualPlan;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetProductionHandoffResource extends JsonResource
{
    public function __construct(
        protected VisualPlan $visualPlan,
        protected array $handoff
    ) {
        parent::__construct($visualPlan);
    }

    /**
     * Transform the production handoff readiness into an array.
     *
     * The status is read raw rather than through the model cast, so a corrupt
     * stored value is reported rather than thrown by the serializer. The issues
     * are pass-through aggregates: each one already names its source and
     * carries only metadata the upstream readiness/manifest layers already
     * exposed, so there is no file path and no URL anywhere in this payload.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'visual_plan_id' => $this->visualPlan->id,
            'script_version_id' => $this->visualPlan->script_version_id,
            'status' => $this->visualPlan->getRawOriginal('status'),
            'ready' => $this->handoff['ready'],
            'summary' => $this->handoff['summary'],
            'issues' => $this->handoff['issues'],
        ];
    }
}
