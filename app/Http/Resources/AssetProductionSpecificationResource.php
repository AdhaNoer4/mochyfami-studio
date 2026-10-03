<?php

namespace App\Http\Resources;

use App\Models\VisualPlan;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetProductionSpecificationResource extends JsonResource
{
    public function __construct(
        protected VisualPlan $visualPlan,
        protected array $specification
    ) {
        parent::__construct($visualPlan);
    }

    /**
     * Transform the production specification into an array.
     *
     * The status is read raw rather than through the model cast, matching the
     * sibling manifest/handoff resources. Segments carry ids, types, and
     * durations only - never a file path or URL.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'visual_plan_id' => $this->visualPlan->id,
            'script_version_id' => $this->visualPlan->script_version_id,
            'status' => $this->visualPlan->getRawOriginal('status'),
            'ready' => $this->specification['ready'],
            'specification' => $this->specification['specification'],
            'segments' => $this->specification['segments'],
            'issues' => $this->specification['issues'],
        ];
    }
}
