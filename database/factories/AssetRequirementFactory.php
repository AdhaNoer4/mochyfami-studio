<?php

namespace Database\Factories;

use App\Enums\AssetRequirementAspectRatio;
use App\Enums\AssetRequirementStatus;
use App\Enums\AssetRequirementType;
use App\Models\AssetRequirement;
use App\Models\VisualPlanItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetRequirement>
 */
class AssetRequirementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visual_plan_item_id' => VisualPlanItem::factory(),
            'requirement_type' => AssetRequirementType::Video,
            'search_query' => $this->faker->sentence(3),
            'description' => $this->faker->sentence(),
            'target_duration_seconds' => $this->faker->numberBetween(1, 60),
            'aspect_ratio' => AssetRequirementAspectRatio::Portrait916,
            'status' => AssetRequirementStatus::Pending,
            'notes' => null,
        ];
    }
}
