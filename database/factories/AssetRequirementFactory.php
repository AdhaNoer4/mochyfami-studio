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
            'selected_asset_id' => null,
            'status' => AssetRequirementStatus::Pending,
            'notes' => null,
        ];
    }

    /**
     * A requirement that has been picked up and is ready to be worked on.
     *
     * Fulfillment is only reachable from searching, so most fulfillment tests
     * need this state rather than the default pending one.
     */
    public function searching(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssetRequirementStatus::Searching,
        ]);
    }
}
