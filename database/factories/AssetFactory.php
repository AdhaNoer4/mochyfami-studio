<?php

namespace Database\Factories;

use App\Enums\AssetMetadataStatus;
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

    /**
     * An asset with a stored file whose technical metadata was read
     * successfully.
     *
     * A file path is required before metadata can be completed at all, since
     * inspection reads the disk, so this state sets all three together: path,
     * completed status, and the measured values. Width and height default to
     * portrait 1080x1920, which is what the studio produces and what the
     * default 9:16 requirement asks for.
     */
    public function withVerifiedFile(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssetStatus::Available,
            'file_path' => 'projects/'.$this->faker->uuid().'/asset.mp4',
            'file_name' => 'asset.mp4',
            'mime_type' => 'video/mp4',
            'file_size' => $this->faker->numberBetween(1024, 1024 * 1024),
            'duration_seconds' => 30,
            'width' => 1080,
            'height' => 1920,
            'aspect_ratio' => 0.5625,
            'metadata_status' => AssetMetadataStatus::Completed,
            'metadata_error' => null,
            'metadata_analyzed_at' => now(),
        ]);
    }

    /**
     * A file is attached but nobody has read its technical details yet.
     *
     * This is the state an upload lands in, and it is deliberately not
     * compatible with anything: the asset has a file, so it looks plausible,
     * but nothing about it has been verified.
     */
    public function withUnverifiedFile(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssetStatus::Available,
            'file_path' => 'projects/'.$this->faker->uuid().'/asset.mp4',
            'file_name' => 'asset.mp4',
            'mime_type' => 'video/mp4',
            'metadata_status' => AssetMetadataStatus::Pending,
            'metadata_error' => null,
            'metadata_analyzed_at' => null,
        ]);
    }

    /**
     * A file is attached but could not be read.
     */
    public function withUnreadableFile(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AssetStatus::Available,
            'file_path' => 'projects/'.$this->faker->uuid().'/asset.mp4',
            'file_name' => 'asset.mp4',
            'mime_type' => 'video/mp4',
            'metadata_status' => AssetMetadataStatus::Failed,
            'metadata_error' => 'The file could not be read.',
            'metadata_analyzed_at' => now(),
        ]);
    }
}
