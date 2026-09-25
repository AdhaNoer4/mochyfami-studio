<?php

namespace Database\Factories;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResearchClaim>
 */
class ResearchClaimFactory extends Factory
{
    protected $model = ResearchClaim::class;

    public function definition(): array
    {
        return [
            'research_report_id' => ResearchReport::factory(),
            'claim' => $this->faker->sentence(),
            'status' => $this->faker->randomElement(ResearchClaimStatus::cases()),
            'importance' => $this->faker->randomElement(ResearchClaimImportance::cases()),
        ];
    }
}
