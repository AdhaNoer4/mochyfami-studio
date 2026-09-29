<?php

namespace App\Http\Resources;

use App\Models\VisualPlan;
use Illuminate\Http\Resources\Json\JsonResource;

class VisualPlanQualityResource extends JsonResource
{
    public function __construct(
        protected VisualPlan $visualPlan,
        protected array $quality
    ) {
        parent::__construct($visualPlan);
    }

    /**
     * Transform the visual plan readiness evaluation into an array.
     *
     * The status is read raw rather than through the model cast: a corrupt
     * stored value must be reported by the gate, not thrown by the serializer
     * on its way out.
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
            'blockers' => $this->quality['blockers'],
            'warnings' => $this->quality['warnings'],
            'info' => $this->quality['info'],
        ];
    }
}
