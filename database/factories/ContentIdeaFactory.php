<?php

namespace Database\Factories;

use App\Enums\ContentFormat;
use App\Enums\ContentIdeaStatus;
use App\Models\ContentCategory;
use App\Models\ContentIdea;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContentIdea>
 */
class ContentIdeaFactory extends Factory
{
    protected $model = ContentIdea::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(4);

        return [
            'category_id' => ContentCategory::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numberBetween(100, 999),
            'hook' => 'Did you know '.$this->faker->sentence().'?',
            'concept' => $this->faker->paragraph(),
            'format' => $this->faker->randomElement(ContentFormat::cases()),
            'status' => $this->faker->randomElement(ContentIdeaStatus::cases()),
            'priority' => $this->faker->numberBetween(1, 3),
            'notes' => $this->faker->sentence(),
            'source_idea' => 'YouTube research',
            'created_by' => User::factory(),
        ];
    }
}
