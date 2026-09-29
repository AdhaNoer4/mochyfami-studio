<?php

namespace Database\Factories;

use App\Enums\VisualPlanStatus;
use App\Models\ContentProject;
use App\Models\ScriptVersion;
use App\Models\VisualPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisualPlan>
 */
class VisualPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content_project_id' => ContentProject::factory(),
            'script_version_id' => ScriptVersion::factory(),
            'status' => VisualPlanStatus::Draft,
            'title' => null,
            'notes' => null,
        ];
    }
}
