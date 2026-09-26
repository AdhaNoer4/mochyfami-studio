<?php

namespace Database\Factories;

use App\Models\Script;
use App\Models\ScriptVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScriptVersion>
 */
class ScriptVersionFactory extends Factory
{
    protected $model = ScriptVersion::class;

    public function definition(): array
    {
        return [
            'script_id' => Script::factory(),
            'version' => 1,
            'title' => $this->faker->sentence(5),
            'hook' => $this->faker->sentence(),
            'body' => $this->faker->paragraphs(3, true),
            'closing' => $this->faker->sentence(),
            'duration_seconds' => $this->faker->numberBetween(30, 60),
            'notes' => $this->faker->sentence(),
        ];
    }
}
