<?php

namespace Database\Factories;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\ContentProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The default is a pending video with descriptive metadata and no file at
     * all, which is the only state this part can honestly produce.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content_project_id' => ContentProject::factory(),
            'type' => AssetType::Video,
            'status' => AssetStatus::Pending,
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->sentence(),
            'file_path' => null,
            'file_name' => null,
            'mime_type' => null,
            'file_size' => null,
            'duration_seconds' => null,
            'width' => null,
            'height' => null,
            'source_url' => $this->faker->url(),
            'source_name' => $this->faker->company(),
            'license_type' => null,
            'attribution' => null,
            'notes' => null,
        ];
    }
}
