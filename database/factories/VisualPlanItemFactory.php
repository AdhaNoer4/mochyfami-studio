<?php

namespace Database\Factories;

use App\Enums\VisualPlanItemType;
use App\Enums\VisualPlanSection;
use App\Models\VisualPlan;
use App\Models\VisualPlanItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisualPlanItem>
 */
class VisualPlanItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visual_plan_id' => VisualPlan::factory(),
            'order' => 1,
            'section' => VisualPlanSection::Hook,
            'narration_text' => $this->faker->sentence(),
            'visual_type' => VisualPlanItemType::AnimalClip,
            'visual_prompt' => $this->faker->sentence(),
            'duration_seconds' => $this->faker->numberBetween(1, 60),
            'notes' => null,
        ];
    }
}
