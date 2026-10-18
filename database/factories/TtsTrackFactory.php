<?php

namespace Database\Factories;

use App\Enums\ManualDubbingApprovalStatus;
use App\Enums\TtsTrackSource;
use App\Enums\TtsTrackStatus;
use App\Models\ContentProject;
use App\Models\TtsTrack;
use App\Models\User;
use App\Models\VisualPlanItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TtsTrack>
 */
class TtsTrackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The default is a pending track with no asset yet, which is the honest
     * starting state: no synthesis has happened until the generation service
     * runs, and content_project_id is set through a project's own relation.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $narration = 'Narasi contoh untuk pengujian voiceover studio.';

        return [
            'content_project_id' => ContentProject::factory(),
            'visual_plan_item_id' => VisualPlanItem::factory(),
            'asset_id' => null,
            'provider' => 'fake',
            'model' => null,
            'voice' => 'neutral',
            'language' => 'id',
            'speed' => 1.000,
            'status' => TtsTrackStatus::Pending,
            'duration_seconds' => null,
            'narration_snapshot' => $narration,
            'narration_hash' => hash('sha256', $narration),
            'error' => null,
        ];
    }

    /**
     * A finished synthesis whose audio already has a measured duration.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TtsTrackStatus::Completed,
            'duration_seconds' => 30,
        ]);
    }

    /**
     * A synthesis that could not be produced.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TtsTrackStatus::Failed,
            'error' => 'The TTS provider could not synthesize this narration.',
        ]);
    }

    /**
     * A historical track that was archived by a delete handler.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TtsTrackStatus::Archived,
        ]);
    }

    /**
     * A human-recorded dubbing rendition.
     *
     * provider and voice keep the strings 'manual' because the columns are
     * still NOT NULL: a human source has no synthesis provider, and the
     * recording metadata lives in the manual-dubbing columns instead.
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => TtsTrackSource::Manual,
            'provider' => 'manual',
            'voice' => 'manual',
            'uploader_id' => User::factory(),
            'recorded_at' => now(),
            'approval_status' => ManualDubbingApprovalStatus::Draft,
        ]);
    }
}
