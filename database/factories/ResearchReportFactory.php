<?php

namespace Database\Factories;

use App\Enums\ResearchStatus;
use App\Models\ContentProject;
use App\Models\ResearchReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResearchReport>
 */
class ResearchReportFactory extends Factory
{
    protected $model = ResearchReport::class;

    public function definition(): array
    {
        return [
            'content_project_id' => ContentProject::factory(),
            'status' => ResearchStatus::Pending,
            'summary' => null,
            'researched_at' => null,
        ];
    }
}
