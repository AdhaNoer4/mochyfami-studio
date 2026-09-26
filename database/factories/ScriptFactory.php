<?php

namespace Database\Factories;

use App\Enums\ScriptStatus;
use App\Models\ContentProject;
use App\Models\Script;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Script>
 */
class ScriptFactory extends Factory
{
    protected $model = Script::class;

    public function definition(): array
    {
        return [
            'content_project_id' => ContentProject::factory(),
            'status' => ScriptStatus::Draft,
            'current_version_id' => null,
        ];
    }
}
