<?php

namespace Database\Factories;

use App\Enums\SourceType;
use App\Models\ResearchReport;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Source>
 */
class SourceFactory extends Factory
{
    protected $model = Source::class;

    public function definition(): array
    {
        $url = $this->faker->url();

        return [
            'research_report_id' => ResearchReport::factory(),
            'title' => $this->faker->sentence(5),
            'url' => $url,
            'domain' => parse_url($url, PHP_URL_HOST),
            'source_type' => $this->faker->randomElement(SourceType::cases()),
            'published_at' => null,
        ];
    }
}
