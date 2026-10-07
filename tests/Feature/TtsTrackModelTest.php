<?php

namespace Tests\Feature;

use App\Enums\TtsTrackStatus;
use App\Models\Asset;
use App\Models\ContentProject;
use App\Models\TtsTrack;
use App\Models\User;
use App\Models\VisualPlanItem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TtsTrackModelTest extends TestCase
{
    use RefreshDatabase;

    private function projectFor(User $user): ContentProject
    {
        return ContentProject::factory()->for($user, 'creator')->create();
    }

    public function test_a_track_belongs_to_the_project_it_was_created_under(): void
    {
        $project = $this->projectFor(User::factory()->create());

        $track = TtsTrack::factory()->create(['content_project_id' => $project->id]);

        $this->assertSame($project->id, $track->contentProject->id);
        $this->assertTrue($project->ttsTracks->contains($track));
    }

    public function test_a_track_belongs_to_its_visual_plan_item(): void
    {
        $item = VisualPlanItem::factory()->create();

        $track = TtsTrack::factory()->create(['visual_plan_item_id' => $item->id]);

        $this->assertSame($item->id, $track->visualPlanItem->id);
    }

    public function test_the_asset_relation_defaults_to_null(): void
    {
        $track = TtsTrack::factory()->create();

        $this->assertNull($track->asset_id);
        $this->assertNull($track->asset);
    }

    public function test_a_track_can_point_to_its_generated_audio_asset(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $asset = Asset::factory()->create(['content_project_id' => $project->id]);

        $track = TtsTrack::factory()->create([
            'content_project_id' => $project->id,
            'asset_id' => $asset->id,
        ]);

        $this->assertSame($asset->id, $track->asset->id);
    }

    public function test_status_and_speed_are_cast(): void
    {
        $track = TtsTrack::factory()->create([
            'status' => TtsTrackStatus::Completed,
            'speed' => '0.750',
        ])->fresh();

        $this->assertInstanceOf(TtsTrackStatus::class, $track->status);
        $this->assertSame(TtsTrackStatus::Completed, $track->status);
        $this->assertSame('0.750', $track->speed);
    }

    public function test_duration_seconds_is_cast_to_an_integer(): void
    {
        $track = TtsTrack::factory()->create(['duration_seconds' => '12'])->fresh();

        $this->assertSame(12, $track->duration_seconds);
    }

    public function test_a_project_id_is_required_by_the_foreign_key(): void
    {
        $this->expectException(QueryException::class);

        TtsTrack::factory()->create(['content_project_id' => 999999]);
    }

    public function test_deleting_a_project_cascades_to_its_tracks(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $track = TtsTrack::factory()->create(['content_project_id' => $project->id]);

        $project->delete();

        $this->assertNull(TtsTrack::find($track->id));
    }

    public function test_deleting_a_visual_plan_item_cascades_to_its_tracks(): void
    {
        $item = VisualPlanItem::factory()->create();
        $track = TtsTrack::factory()->create(['visual_plan_item_id' => $item->id]);

        $item->delete();

        $this->assertNull(TtsTrack::find($track->id));
    }

    public function test_deleting_an_asset_nulls_the_track_asset_id_instead_of_deleting_history(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $asset = Asset::factory()->create(['content_project_id' => $project->id]);
        $track = TtsTrack::factory()->create([
            'content_project_id' => $project->id,
            'asset_id' => $asset->id,
        ]);

        $asset->delete();

        $this->assertNotNull(TtsTrack::find($track->id));
        $this->assertNull($track->fresh()->asset_id);
    }

    public function test_narration_hash_is_a_64_character_sha256_of_the_snapshot(): void
    {
        $track = TtsTrack::factory()->create();

        $this->assertSame(64, strlen($track->narration_hash));
        $this->assertSame(hash('sha256', $track->narration_snapshot), $track->narration_hash);
    }

    #[DataProvider('statusProvider')]
    public function test_every_status_can_be_stored(TtsTrackStatus $status): void
    {
        $track = TtsTrack::factory()->create(['status' => $status]);

        $this->assertSame($status, $track->fresh()->status);
    }

    public static function statusProvider(): array
    {
        return [
            'pending' => [TtsTrackStatus::Pending],
            'generating' => [TtsTrackStatus::Generating],
            'completed' => [TtsTrackStatus::Completed],
            'failed' => [TtsTrackStatus::Failed],
            'archived' => [TtsTrackStatus::Archived],
        ];
    }
}
