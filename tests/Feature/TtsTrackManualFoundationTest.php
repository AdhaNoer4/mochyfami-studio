<?php

namespace Tests\Feature;

use App\Enums\ManualDubbingApprovalStatus;
use App\Enums\TtsTrackSource;
use App\Enums\TtsTrackStatus;
use App\Models\ContentProject;
use App\Models\TtsTrack;
use App\Models\User;
use App\Models\VisualPlanItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TtsTrackManualFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function projectFor(User $user): ContentProject
    {
        return ContentProject::factory()->for($user, 'creator')->create();
    }

    public function test_a_new_track_defaults_to_the_tts_source(): void
    {
        $track = TtsTrack::factory()->create();

        $this->assertSame(TtsTrackSource::Tts, $track->source);
        $this->assertSame(TtsTrackSource::Tts, $track->fresh()->source);
    }

    public function test_a_manual_track_stores_recording_and_approval_metadata(): void
    {
        $uploader = User::factory()->create();
        $approver = User::factory()->create();
        $recordedAt = now()->subMinutes(5);
        $approvedAt = now()->subMinute();

        $track = TtsTrack::factory()->manual()->create([
            'uploader_id' => $uploader->id,
            'recorded_at' => $recordedAt,
            'approval_status' => ManualDubbingApprovalStatus::Approved,
            'approved_at' => $approvedAt,
            'approved_by' => $approver->id,
            'notes' => 'Take kedua, artikulasi lebih jelas.',
        ])->fresh();

        $this->assertSame(TtsTrackSource::Manual, $track->source);
        $this->assertSame(ManualDubbingApprovalStatus::Approved, $track->approval_status);
        $this->assertSame($uploader->id, $track->uploader_id);
        $this->assertSame($approver->id, $track->approved_by);
        $this->assertSame($recordedAt->toDateTimeString(), $track->recorded_at->toDateTimeString());
        $this->assertSame($approvedAt->toDateTimeString(), $track->approved_at->toDateTimeString());
        $this->assertSame('Take kedua, artikulasi lebih jelas.', $track->notes);
    }

    public function test_uploader_and_approver_relations_return_the_owning_users(): void
    {
        $uploader = User::factory()->create();
        $approver = User::factory()->create();

        $track = TtsTrack::factory()->manual()->create([
            'uploader_id' => $uploader->id,
            'approval_status' => ManualDubbingApprovalStatus::ReadyForReview,
            'approved_by' => $approver->id,
        ]);

        $this->assertSame($uploader->id, $track->uploader->id);
        $this->assertSame($approver->id, $track->approver->id);
    }

    public function test_an_existing_tts_track_keeps_its_lifecycle_and_relations(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $item = VisualPlanItem::factory()->create();

        $track = TtsTrack::factory()->completed()->create([
            'content_project_id' => $project->id,
            'visual_plan_item_id' => $item->id,
        ]);

        $this->assertSame(TtsTrackSource::Tts, $track->source);
        $this->assertNull($track->approval_status);
        $this->assertNull($track->uploader_id);
        $this->assertSame($project->id, $track->contentProject->id);
        $this->assertSame($item->id, $track->visualPlanItem->id);

        $track->transitionTo(TtsTrackStatus::Archived);

        $this->assertSame(TtsTrackStatus::Archived, $track->fresh()->status);
    }

    public function test_a_visual_plan_item_may_own_several_track_renditions(): void
    {
        $item = VisualPlanItem::factory()->create();

        TtsTrack::factory()->count(2)->create(['visual_plan_item_id' => $item->id]);
        TtsTrack::factory()->manual()->create(['visual_plan_item_id' => $item->id]);

        $this->assertSame(3, TtsTrack::where('visual_plan_item_id', $item->id)->count());
    }

    public function test_approval_status_is_stored_independently_of_lifecycle_status(): void
    {
        $rejected = TtsTrack::factory()->manual()->completed()->create([
            'approval_status' => ManualDubbingApprovalStatus::Rejected,
        ])->fresh();

        $this->assertSame(TtsTrackStatus::Completed, $rejected->status);
        $this->assertSame(ManualDubbingApprovalStatus::Rejected, $rejected->approval_status);

        $draft = TtsTrack::factory()->manual()->create([
            'approval_status' => ManualDubbingApprovalStatus::Draft,
        ])->fresh();

        $this->assertSame(TtsTrackStatus::Pending, $draft->status);
        $this->assertSame(ManualDubbingApprovalStatus::Draft, $draft->approval_status);
    }

    #[DataProvider('manualApprovalStatusProvider')]
    public function test_every_manual_approval_status_can_be_stored(ManualDubbingApprovalStatus $status): void
    {
        $track = TtsTrack::factory()->manual()->create([
            'approval_status' => $status,
        ])->fresh();

        $this->assertSame($status, $track->approval_status);
    }

    public static function manualApprovalStatusProvider(): array
    {
        return [
            'draft' => [ManualDubbingApprovalStatus::Draft],
            'ready_for_review' => [ManualDubbingApprovalStatus::ReadyForReview],
            'approved' => [ManualDubbingApprovalStatus::Approved],
            'rejected' => [ManualDubbingApprovalStatus::Rejected],
        ];
    }

    public function test_a_row_inserted_without_a_source_keeps_the_legacy_tts_meaning(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $item = VisualPlanItem::factory()->create();
        $narration = 'Narasi warisan sebelum kolom source ditambahkan.';

        DB::table('tts_tracks')->insert([
            'content_project_id' => $project->id,
            'visual_plan_item_id' => $item->id,
            'provider' => 'fake',
            'voice' => 'neutral',
            'status' => TtsTrackStatus::Completed->value,
            'narration_snapshot' => $narration,
            'narration_hash' => hash('sha256', $narration),
        ]);

        $track = TtsTrack::latest('id')->first();

        $this->assertSame('tts', DB::table('tts_tracks')->where('id', $track->id)->value('source'));
        $this->assertSame(TtsTrackSource::Tts, $track->source);
        $this->assertSame(TtsTrackStatus::Completed, $track->status);
        $this->assertNull($track->approval_status);
    }

    public function test_deleting_a_user_keeps_the_dubbing_history_it_uploaded(): void
    {
        $uploader = User::factory()->create();
        $approver = User::factory()->create();

        $track = TtsTrack::factory()->manual()->create([
            'uploader_id' => $uploader->id,
            'approval_status' => ManualDubbingApprovalStatus::Approved,
            'approved_by' => $approver->id,
        ]);

        $uploader->delete();
        $approver->delete();

        $track->refresh();

        $this->assertNotNull(TtsTrack::find($track->id));
        $this->assertNull($track->uploader_id);
        $this->assertNull($track->approved_by);
    }
}
