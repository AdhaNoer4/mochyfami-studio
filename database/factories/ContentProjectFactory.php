<?php

namespace Database\Factories;

use App\Enums\ContentProjectStatus;
use App\Models\ContentCategory;
use App\Models\ContentIdea;
use App\Models\ContentProject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContentProject>
 */
class ContentProjectFactory extends Factory
{
    protected $model = ContentProject::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(4);

        return [
            'content_idea_id' => ContentIdea::factory(),
            'category_id' => ContentCategory::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numberBetween(100, 999),
            'status' => $this->faker->randomElement(ContentProjectStatus::cases()),
            'target_duration_seconds' => $this->faker->numberBetween(30, 60),
            'language' => 'en',
            'tone' => 'informative',
            'hook' => 'Did you know '.$this->faker->sentence().'?',
            'description' => $this->faker->paragraph(),
            'current_step' => 'drafting',
            'progress_percent' => $this->faker->numberBetween(0, 100),
            'created_by' => User::factory(),
        ];
    }
}
