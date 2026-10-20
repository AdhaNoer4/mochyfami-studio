<?php

namespace Tests\Feature;

use App\Enums\AssetType;
use App\Enums\ManualDubbingApprovalStatus;
use App\Enums\ScriptStatus;
use App\Enums\TtsTrackSource;
use App\Enums\TtsTrackStatus;
use App\Enums\VisualPlanItemType;
use App\Enums\VisualPlanSection;
use App\Models\Asset;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Script;
use App\Models\Source;
use App\Models\TtsTrack;
use App\Models\User;
use App\Models\VisualPlan;
use App\Models\VisualPlanItem;
use App\Services\AssetFileStorageService;
use App\Services\AssetMetadataService;
use App\Services\Metadata\AssetMetadataInspector;
use App\Services\TTS\TtsManifestService;
use App\Services\TTS\TtsReadinessService;
use App\Services\VisualPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ManualDubbingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake((string) config('assets.disk'));

        // Duration measurement has to be predictable: a fake inspector
        // answers with a fixed duration instead of anything depending on a
        // decoder, the same way the synthesis tests do.
        $this->withInspector($this->inspectorReporting(
            AssetType::Audio,
            ['width' => null, 'height' => null, 'duration_seconds' => 7]
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function readyProject(User $user): ContentProject
    {
        $project = ContentProject::factory()->for($user, 'creator')->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'claim' => 'Cats purr to communicate with humans',
            'status' => 'supported',
            'importance' => 'high',
        ]);
        $claim->sources()->attach($source->id);

        return $project;
    }

    private function planWithItems(ContentProject $project, int $version = 1, int $itemCount = 1): VisualPlan
    {
        $script = $project->script()->first() ?? Script::factory()->create(['content_project_id' => $project->id]);

        $versionModel = $script->versions()->create([
            'version' => $version,
            'title' => 'How Cats Purr',
            'hook' => 'Why do cats purr?',
            'body' => "Cats purr to communicate with humans.\n",
            'closing' => 'And now you know.',
            'duration_seconds' => 45,
            'notes' => null,
        ]);

        $script->update(['current_version_id' => $versionModel->id, 'status' => ScriptStatus::Review]);
        $script->load('currentVersion');

        $plan = app(VisualPlanService::class)->createVisualPlan($project, $version, []);

        foreach (range(1, $itemCount) as $index) {
            $plan->items()->create([
                'order' => $index,
                'section' => VisualPlanSection::Body->value,
                'narration_text' => "Narration line {$index}.",
                'visual_type' => VisualPlanItemType::StockVideo->value,
                'visual_prompt' => "Visual prompt {$index}.",
                'duration_seconds' => 5,
                'notes' => null,
            ]);
        }

        return $plan;
    }

    private function manualUrl(ContentProject $project, int $version, int $item): string
    {
        return "/api/v1/projects/{$project->id}/script/versions/{$version}/visual-plan/items/{$item}/manual-dubbing";
    }

    private function ttsUrl(ContentProject $project, int $version, int $item): string
    {
        return "/api/v1/projects/{$project->id}/script/versions/{$version}/visual-plan/items/{$item}/tts";
    }

    private function audioFile(string $name = 'recording.mp3'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 4, 'audio/mpeg');
    }

    private function storedFiles(): array
    {
        return Storage::disk((string) config('assets.disk'))->allFiles();
    }

    private function uploadPayload(array $overrides = []): array
    {
        return array_merge([
            'file' => $this->audioFile(),
        ], $overrides);
    }

    private function withInspector(AssetMetadataInspector $inspector): void
    {
        $this->app->instance(
            AssetMetadataService::class,
            new AssetMetadataService(new AssetFileStorageService, [$inspector])
        );
    }

    private function inspectorReporting(AssetType $type, array $result, ?\Throwable $failure = null): AssetMetadataInspector
    {
        return new class($type, $result, $failure) implements AssetMetadataInspector
        {
            public function __construct(
                private AssetType $type,
                private array $result,
                private ?\Throwable $failure
            ) {}

            public function handles(): AssetType
            {
                return $this->type;
            }

            public function inspect(string $absolutePath, string $mimeType): array
            {
                if ($this->failure !== null) {
                    throw $this->failure;
                }

                return $this->result;
            }
        };
    }

    private function manualTrackFor(ContentProject $project, VisualPlanItem $item, array $overrides = []): TtsTrack
    {
        return TtsTrack::factory()->manual()->completed()->create(array_merge([
            'content_project_id' => $project->getKey(),
            'visual_plan_item_id' => $item->getKey(),
        ], $overrides));
    }

    private function ttsTrackFor(ContentProject $project, VisualPlanItem $item, array $overrides = []): TtsTrack
    {
        $narration = (string) $item->narration_text;

        return TtsTrack::factory()->completed()->create(array_merge([
            'content_project_id' => $project->getKey(),
            'visual_plan_item_id' => $item->getKey(),
            'narration_snapshot' => $narration,
            'narration_hash' => hash('sha256', $narration),
        ], $overrides));
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_manual_dubbing_endpoints_require_authentication(): void
    {
        $base = '/api/v1/projects/1/script/versions/1/visual-plan/items/1/manual-dubbing';

        $this->get($base, ['Accept' => 'application/json'])->assertUnauthorized();
        $this->post($base, $this->uploadPayload())->assertUnauthorized();
        $this->patchJson("{$base}/1/submit")->assertUnauthorized();
        $this->patchJson("{$base}/1/approve")->assertUnauthorized();
        $this->patchJson("{$base}/1/reject")->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Upload
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_a_valid_audio_upload_creates_a_completed_manual_track_and_private_asset(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project);
        $item = $plan->items()->firstOrFail();

        $response = $this->actingAs($user, 'sanctum')
            ->post($this->manualUrl($project, 1, $item->id), $this->uploadPayload(['notes' => 'Take two.']))
            ->assertCreated()
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.status_label', 'Completed')
            ->assertJsonPath('data.approval_status', 'draft')
            ->assertJsonPath('data.approval_status_label', 'Draft')
            ->assertJsonPath('data.duration_seconds', 7)
            ->assertJsonPath('data.notes', 'Take two.')
            ->assertJsonPath('data.narration_hash', hash('sha256', 'Narration line 1.'))
            ->assertJsonPath('data.is_stale', false)
            ->assertJsonPath('data.uploader.id', $user->getKey())
            ->assertJsonPath('data.uploader.name', $user->name)
            ->assertJsonPath('data.approver', null);

        $this->assertNotNull($response->json('data.recorded_at'));
        $this->assertNull($response->json('data.approved_at'));
        $this->assertSame(
            route('assets.preview', [
                'project' => $project->getKey(),
                'asset' => $response->json('data.asset_id'),
            ]),
            $response->json('data.preview_url')
        );

        $track = TtsTrack::findOrFail($response->json('data.id'));

        $this->assertSame(TtsTrackSource::Manual, $track->source);
        $this->assertSame(TtsTrackStatus::Completed, $track->status);
        $this->assertSame(ManualDubbingApprovalStatus::Draft, $track->approval_status);
        $this->assertSame($user->getKey(), $track->uploader_id);
        $this->assertSame('manual', $track->provider);
        $this->assertSame('manual', $track->voice);
        $this->assertSame(7, $track->duration_seconds);
        $this->assertNotNull($track->recorded_at);
        $this->assertNull($track->approved_at);
        $this->assertNull($track->approved_by);

        $asset = Asset::findOrFail($track->asset_id);

        $this->assertSame(AssetType::Audio, $asset->type);
        $this->assertSame('manual-dubbing', $asset->source_name);
        $this->assertSame('audio/mpeg', $asset->mime_type);
        $this->assertFalse($asset->isTtsGenerated());
        $this->assertNotNull($asset->file_path);
        $this->assertCount(1, $this->storedFiles());
        Storage::disk((string) config('assets.disk'))->assertExists($asset->file_path);
    }

    #[Test]
    public function test_upload_rejects_a_non_audio_file_without_creating_a_track_or_asset(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project);
        $item = $plan->items()->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->post($this->manualUrl($project, 1, $item->id), [
                'file' => UploadedFile::fake()->create('not-audio.png', 4, 'image/png'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'The file must be an audio file.']);

        $this->assertSame(0, TtsTrack::count());
        $this->assertSame(0, Asset::count());
        $this->assertSame([], $this->storedFiles());
    }

    #[Test]
    public function test_upload_rejects_a_file_over_the_size_limit(): void
    {
        config(['assets.upload.max_size_kb' => 1]);

        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project);
        $item = $plan->items()->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->post($this->manualUrl($project, 1, $item->id), [
                'file' => UploadedFile::fake()->create('long.mp3', 8, 'audio/mpeg'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'The file is larger than this application accepts.']);

        $this->assertSame(0, TtsTrack::count());
        $this->assertSame(0, Asset::count());
        $this->assertSame([], $this->storedFiles());
    }

    #[Test]
    public function test_upload_rejects_a_missing_file(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project);
        $item = $plan->items()->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->post($this->manualUrl($project, 1, $item->id), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'An audio file is required.']);
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization and scoping
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_a_non_owner_cannot_read_or_write_another_projects_manual_dubbing(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = $this->readyProject($owner);
        $plan = $this->planWithItems($project);
        $item = $plan->items()->firstOrFail();
        $track = $this->manualTrackFor($project, $item);

        $this->actingAs($stranger, 'sanctum')
            ->getJson($this->manualUrl($project, 1, $item->id))
            ->assertForbidden();

        $this->actingAs($stranger, 'sanctum')
            ->post($this->manualUrl($project, 1, $item->id), $this->uploadPayload())
            ->assertForbidden();

        $this->actingAs($stranger, 'sanctum')
            ->patchJson("{$this->manualUrl($project, 1, $item->id)}/{$track->id}/submit")
            ->assertForbidden();

        $this->actingAs($stranger, 'sanctum')
            ->patchJson("{$this->manualUrl($project, 1, $item->id)}/{$track->id}/approve")
            ->assertForbidden();

        $this->actingAs($stranger, 'sanctum')
            ->patchJson("{$this->manualUrl($project, 1, $item->id)}/{$track->id}/reject")
            ->assertForbidden();

        // The pre-seeded draft track is the only row: the refused upload wrote
        // no new track and no asset, and the review actions changed nothing.
        $this->assertSame(1, TtsTrack::count());
        $this->assertSame(0, Asset::count());
        $this->assertSame(ManualDubbingApprovalStatus::Draft, $track->fresh()->approval_status);
    }

    #[Test]
    public function test_mismatched_nested_ids_answer_404(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project, 1, 2);
        $items = $plan->items()->reorder('order')->get();
        $first = $items->first();
        $second = $items->last();
        $manual = $this->manualTrackFor($project, $first);
        $ttsTrack = $this->ttsTrackFor($project, $first);

        // A script version that does not have a plan.
        $this->actingAs($user, 'sanctum')
            ->getJson($this->manualUrl($project, 99, $first->id))
            ->assertNotFound();

        // An item that does not belong to this plan.
        $this->actingAs($user, 'sanctum')
            ->post($this->manualUrl($project, 1, 999), $this->uploadPayload())
            ->assertNotFound();

        // A track that does not belong to the given item.
        $foreignTrack = $this->manualTrackFor($project, $second);

        $this->actingAs($user, 'sanctum')
            ->patchJson("{$this->manualUrl($project, 1, $first->id)}/{$foreignTrack->id}/submit")
            ->assertNotFound();

        // An automated TTS track is not addressable through the manual API.
        $this->actingAs($user, 'sanctum')
            ->patchJson("{$this->manualUrl($project, 1, $first->id)}/{$ttsTrack->id}/approve")
            ->assertNotFound();

        // An unknown track id.
        $unknownTrackId = $manual->id + 999;

        $this->actingAs($user, 'sanctum')
            ->patchJson("{$this->manualUrl($project, 1, $first->id)}/{$unknownTrackId}/submit")
            ->assertNotFound();

        // A track id from another project.
        $otherOwner = User::factory()->create();
        $otherProject = $this->readyProject($otherOwner);
        $otherPlan = $this->planWithItems($otherProject);
        $otherItem = $otherPlan->items()->firstOrFail();
        $otherTrack = $this->manualTrackFor($otherProject, $otherItem);

        $this->actingAs($user, 'sanctum')
            ->patchJson("{$this->manualUrl($project, 1, $first->id)}/{$otherTrack->id}/submit")
            ->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | History separation and staleness
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_manual_history_lists_only_manual_tracks_newest_first(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project);
        $item = $plan->items()->firstOrFail();

        $older = $this->manualTrackFor($project, $item);
        $newer = $this->manualTrackFor($project, $item);
        $this->ttsTrackFor($project, $item);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->manualUrl($project, 1, $item->id))
            ->assertOk();

        $this->assertSame([$newer->id, $older->id], array_column($response->json('data.tracks'), 'id'));
    }

    #[Test]
    public function test_automated_tts_history_lists_only_tts_tracks(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project);
        $item = $plan->items()->firstOrFail();

        $this->manualTrackFor($project, $item);
        $ttsTrack = $this->ttsTrackFor($project, $item);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->ttsUrl($project, 1, $item->id))
            ->assertOk();

        $this->assertSame([$ttsTrack->id], array_column($response->json('data.tracks'), 'id'));
    }

    #[Test]
    public function test_a_manual_track_becomes_stale_when_the_item_narration_changes(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project);
        $item = $plan->items()->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->post($this->manualUrl($project, 1, $item->id), $this->uploadPayload())
            ->assertCreated();

        $item->update(['narration_text' => 'A rewritten narration line.']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->manualUrl($project, 1, $item->id))
            ->assertOk();

        $this->assertCount(1, $response->json('data.tracks'));
        $this->assertTrue($response->json('data.tracks.0.is_stale'));
    }

    #[Test]
    public function test_uploading_a_replacement_keeps_the_previous_track_and_asset(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project);
        $item = $plan->items()->firstOrFail();

        $firstResponse = $this->actingAs($user, 'sanctum')
            ->post($this->manualUrl($project, 1, $item->id), $this->uploadPayload(['file' => $this->audioFile('first.mp3')]))
            ->assertCreated();

        $firstTrack = TtsTrack::findOrFail($firstResponse->json('data.id'));
        $firstAssetPath = $firstTrack->asset->file_path;

        $secondResponse = $this->actingAs($user, 'sanctum')
            ->post($this->manualUrl($project, 1, $item->id), $this->uploadPayload(['file' => $this->audioFile('second.mp3')]))
            ->assertCreated();

        $this->assertNotSame($firstResponse->json('data.id'), $secondResponse->json('data.id'));
        $this->assertNotSame($firstResponse->json('data.asset_id'), $secondResponse->json('data.asset_id'));

        // The original track, its asset, and its file all survive untouched.
        $this->assertSame(2, TtsTrack::count());
        $this->assertSame(2, Asset::count());
        $this->assertSame(2, count($this->storedFiles()));
        $this->assertSame($firstAssetPath, $firstTrack->fresh()->asset->file_path);
        $this->assertSame($firstTrack->asset_id, $firstTrack->fresh()->asset_id);
        Storage::disk((string) config('assets.disk'))->assertExists($firstAssetPath);

        $listed = $this->actingAs($user, 'sanctum')
            ->getJson($this->manualUrl($project, 1, $item->id))
            ->assertOk();

        $this->assertSame(
            [$secondResponse->json('data.id'), $firstResponse->json('data.id')],
            array_column($listed->json('data.tracks'), 'id')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Approval workflow
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_submit_moves_a_draft_to_ready_for_review_without_touching_lifecycle_status(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project);
        $item = $plan->items()->firstOrFail();
        $track = $this->manualTrackFor($project, $item);

        $this->actingAs($user, 'sanctum')
            ->patchJson("{$this->manualUrl($project, 1, $item->id)}/{$track->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'ready_for_review')
            ->assertJsonPath('data.status', 'completed');

        $fresh = $track->fresh();

        $this->assertSame(ManualDubbingApprovalStatus::ReadyForReview, $fresh->approval_status);
        $this->assertSame(TtsTrackStatus::Completed, $fresh->status);
        $this->assertNull($fresh->approved_at);
        $this->assertNull($fresh->approved_by);
    }

    #[Test]
    public function test_approve_stamps_server_side_provenance_and_keeps_lifecycle_status(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project);
        $item = $plan->items()->firstOrFail();
        $track = $this->manualTrackFor($project, $item, ['approval_status' => ManualDubbingApprovalStatus::ReadyForReview]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("{$this->manualUrl($project, 1, $item->id)}/{$track->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'approved')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.approver.id', $user->getKey())
            ->assertJsonPath('data.approver.name', $user->name);

        $this->assertNotNull($response->json('data.approved_at'));

        $fresh = $track->fresh();

        $this->assertSame(ManualDubbingApprovalStatus::Approved, $fresh->approval_status);
        $this->assertSame(TtsTrackStatus::Completed, $fresh->status);
        $this->assertSame($user->getKey(), $fresh->approved_by);
        $this->assertNotNull($fresh->approved_at);
    }

    #[Test]
    public function test_reject_leaves_the_track_without_approval_provenance(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project);
        $item = $plan->items()->firstOrFail();
        $track = $this->manualTrackFor($project, $item, ['approval_status' => ManualDubbingApprovalStatus::ReadyForReview]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("{$this->manualUrl($project, 1, $item->id)}/{$track->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'rejected')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.approved_at', null)
            ->assertJsonPath('data.approver', null);

        $fresh = $track->fresh();

        $this->assertSame(ManualDubbingApprovalStatus::Rejected, $fresh->approval_status);
        $this->assertSame(TtsTrackStatus::Completed, $fresh->status);
        $this->assertNull($fresh->approved_at);
        $this->assertNull($fresh->approved_by);
    }

    #[Test]
    public function test_transitions_outside_the_approval_graph_are_refused_with_422(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project);
        $item = $plan->items()->firstOrFail();
        $track = $this->manualTrackFor($project, $item);
        $url = "{$this->manualUrl($project, 1, $item->id)}/{$track->id}";

        // A draft cannot be approved or rejected before it is submitted.
        $this->actingAs($user, 'sanctum')->patchJson("{$url}/approve")->assertUnprocessable();
        $this->actingAs($user, 'sanctum')->patchJson("{$url}/reject")->assertUnprocessable();

        // Submitted: reviewable once.
        $this->actingAs($user, 'sanctum')->patchJson("{$url}/submit")->assertOk();
        $this->assertSame(ManualDubbingApprovalStatus::ReadyForReview, $track->fresh()->approval_status);

        // A submitted track cannot be submitted again.
        $this->actingAs($user, 'sanctum')->patchJson("{$url}/submit")->assertUnprocessable();

        // Approved is terminal.
        $this->actingAs($user, 'sanctum')->patchJson("{$url}/approve")->assertOk();
        $this->actingAs($user, 'sanctum')->patchJson("{$url}/submit")->assertUnprocessable();
        $this->actingAs($user, 'sanctum')->patchJson("{$url}/approve")->assertUnprocessable();
        $this->actingAs($user, 'sanctum')->patchJson("{$url}/reject")->assertUnprocessable();
        $this->assertSame(TtsTrackStatus::Completed, $track->fresh()->status);
    }

    #[Test]
    public function test_approval_metadata_cannot_be_forged_through_request_input(): void
    {
        $user = User::factory()->create();
        $impostor = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project);
        $item = $plan->items()->firstOrFail();

        $response = $this->actingAs($user, 'sanctum')
            ->post($this->manualUrl($project, 1, $item->id), $this->uploadPayload([
                'source' => 'tts',
                'provider' => 'forged',
                'voice' => 'forged',
                'status' => 'failed',
                'approval_status' => 'approved',
                'approved_by' => $impostor->getKey(),
                'approved_at' => '2000-01-01T00:00:00Z',
                'recorded_at' => '2000-01-01T00:00:00Z',
                'uploader_id' => $impostor->getKey(),
            ]))
            ->assertCreated()
            ->assertJsonPath('data.source', 'manual')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.approval_status', 'draft')
            ->assertJsonPath('data.uploader.id', $user->getKey());

        $this->assertNull($response->json('data.approved_at'));

        $track = TtsTrack::findOrFail($response->json('data.id'));

        $this->assertSame(TtsTrackSource::Manual, $track->source);
        $this->assertSame(ManualDubbingApprovalStatus::Draft, $track->approval_status);
        $this->assertSame($user->getKey(), $track->uploader_id);
        $this->assertNull($track->approved_by);
        $this->assertNull($track->approved_at);

        $this->actingAs($user, 'sanctum')->patchJson("{$this->manualUrl($project, 1, $item->id)}/{$track->id}/submit")->assertOk();

        $this->actingAs($user, 'sanctum')
            ->patchJson("{$this->manualUrl($project, 1, $item->id)}/{$track->id}/approve", [
                'approval_status' => 'rejected',
                'approved_by' => $impostor->getKey(),
                'approved_at' => '2000-01-01T00:00:00Z',
            ])
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'approved')
            ->assertJsonPath('data.approver.id', $user->getKey());

        $fresh = $track->fresh();

        $this->assertSame($user->getKey(), $fresh->approved_by);
        $this->assertNotSame('2000-01-01T00:00:00Z', $fresh->approved_at?->toIso8601String());
    }

    /*
    |--------------------------------------------------------------------------
    | Isolation, preview, and failure cleanup
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_a_manual_track_never_satisfies_automated_readiness_or_the_manifest(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project);
        $item = $plan->items()->firstOrFail();
        $this->manualTrackFor($project, $item);

        $readiness = app(TtsReadinessService::class)->evaluateWithItems($project, 1);

        $this->assertSame(0, $readiness['info']['completed_tracks']);
        $this->assertFalse($readiness['items'][0]['ready']);

        $manifest = app(TtsManifestService::class)->build($project, 1);

        $this->assertFalse($manifest['items'][0]['audio']['available']);
        $this->assertSame(['ITEM_WITHOUT_TRACK'], array_column($manifest['items'][0]['warnings'], 'code'));
    }

    #[Test]
    public function test_authenticated_preview_serves_the_manual_recording(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project);
        $item = $plan->items()->firstOrFail();

        $response = $this->actingAs($user, 'sanctum')
            ->post($this->manualUrl($project, 1, $item->id), $this->uploadPayload())
            ->assertCreated();

        $asset = Asset::findOrFail($response->json('data.asset_id'));

        $this->actingAs($user, 'sanctum')
            ->get(route('assets.preview', ['project' => $project->getKey(), 'asset' => $asset->getKey()]))
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/mpeg');
    }

    #[Test]
    public function test_a_failed_track_creation_cleans_up_the_asset_and_its_file(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project);
        $item = $plan->items()->firstOrFail();

        // Simulate the database side failing after the asset row and the
        // file already exist: the service must remove both again.
        TtsTrack::creating(function (): void {
            throw new RuntimeException('Simulated track creation failure.');
        });

        $this->actingAs($user, 'sanctum')
            ->post($this->manualUrl($project, 1, $item->id), $this->uploadPayload())
            ->assertStatus(500)
            ->assertJsonPath('message', 'Unable to store the manual dubbing audio.');

        $this->assertSame(0, TtsTrack::count());
        $this->assertSame(0, Asset::count());
        $this->assertSame([], $this->storedFiles());
    }
}
