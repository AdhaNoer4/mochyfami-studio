<?php

namespace Tests\Feature;

use App\Enums\AssetType;
use App\Enums\ScriptStatus;
use App\Enums\TtsTrackStatus;
use App\Enums\VisualPlanItemType;
use App\Enums\VisualPlanSection;
use App\Enums\VisualPlanStatus;
use App\Models\Asset;
use App\Models\ContentProject;
use App\Models\Script;
use App\Models\ScriptVersion;
use App\Models\TtsTrack;
use App\Models\User;
use App\Models\VisualPlan;
use App\Models\VisualPlanItem;
use App\Services\TTS\Contracts\TtsProvider;
use App\Services\TTS\DTO\TtsRequest;
use App\Services\TTS\DTO\TtsResult;
use App\Services\TTS\NarrationHasher;
use App\Services\TTS\TtsProviderRegistry;
use App\Services\TTS\TtsReadinessService;
use App\Services\VisualPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TtsReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TtsReadinessService
    {
        return new TtsReadinessService(
            app(VisualPlanService::class),
            app(TtsProviderRegistry::class)
        );
    }

    private function projectFor(User $user): ContentProject
    {
        return ContentProject::factory()->for($user, 'creator')->create();
    }

    private function scriptVersionFor(ContentProject $project, int $version = 1): ScriptVersion
    {
        $script = $project->script()->first() ?? Script::factory()->create(['content_project_id' => $project->id]);

        $existing = $script->versions()->where('version', $version)->first();

        if ($existing) {
            return $existing;
        }

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

        return $versionModel;
    }

    private function planFor(ContentProject $project, int $version = 1): VisualPlan
    {
        return VisualPlan::factory()->create([
            'content_project_id' => $project->id,
            'script_version_id' => $this->scriptVersionFor($project, $version)->id,
            'status' => VisualPlanStatus::Draft,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function itemFor(VisualPlan $plan, array $overrides = []): VisualPlanItem
    {
        return $plan->items()->create(array_merge([
            'order' => 1,
            'section' => VisualPlanSection::Body->value,
            'narration_text' => 'Cats purr to communicate with humans.',
            'visual_type' => VisualPlanItemType::StockVideo->value,
            'visual_prompt' => 'A cat purring on a blanket.',
            'duration_seconds' => 5,
            'notes' => null,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function trackFor(ContentProject $project, VisualPlanItem $item, array $overrides = []): TtsTrack
    {
        $narration = (string) $item->narration_text;

        return $project->ttsTracks()->create(array_merge([
            'visual_plan_item_id' => $item->getKey(),
            'provider' => 'fake',
            'voice' => 'neutral',
            'language' => 'id',
            'speed' => 1.0,
            'status' => TtsTrackStatus::Completed,
            'duration_seconds' => null,
            'narration_snapshot' => $narration,
            'narration_hash' => NarrationHasher::hash($narration),
            'error' => null,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function verifiedAudio(ContentProject $project, array $overrides = []): Asset
    {
        return Asset::factory()->withVerifiedFile()->create(array_merge([
            'content_project_id' => $project->id,
            'type' => AssetType::Audio,
            'duration_seconds' => 5,
        ], $overrides));
    }

    private function codes(array $issues): array
    {
        return array_column($issues, 'code');
    }

    #[Test]
    public function a_missing_visual_plan_is_a_blocker_not_an_error(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);

        $report = $this->service()->evaluate($project, 1);

        $this->assertFalse($report['ready']);
        $this->assertSame(['NO_VISUAL_PLAN'], $this->codes($report['blockers']));
        $this->assertSame([], $report['warnings']);
        $this->assertSame(0, $report['info']['total_items']);
        $this->assertSame(0, $report['info']['ready_items']);
        $this->assertSame(0, $report['info']['items_without_current_audio']);
    }

    #[Test]
    public function an_empty_visual_plan_is_a_blocker(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $this->planFor($project);

        $report = $this->service()->evaluate($project, 1);

        $this->assertFalse($report['ready']);
        $this->assertSame(['NO_VISUAL_PLAN_ITEMS'], $this->codes($report['blockers']));
        $this->assertSame(0, $report['info']['total_items']);
    }

    #[Test]
    public function an_item_without_narration_is_a_blocker(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan, ['narration_text' => '']);

        $report = $this->service()->evaluate($project, 1);

        $this->assertFalse($report['ready']);
        $blocker = $report['blockers'][0];
        $this->assertSame('ITEM_WITHOUT_NARRATION', $blocker['code']);
        $this->assertSame($item->id, $blocker['visual_plan_item_id']);
        $this->assertSame(0, $report['info']['ready_items']);
    }

    #[Test]
    public function whitespace_only_narration_is_treated_as_missing(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $this->itemFor($plan, ['narration_text' => "   \t  \n  "]);

        $report = $this->service()->evaluate($project, 1);

        $this->assertSame(['ITEM_WITHOUT_NARRATION'], $this->codes($report['blockers']));
    }

    #[Test]
    public function an_item_with_no_history_warns_not_blocks(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        $report = $this->service()->evaluate($project, 1);

        $this->assertTrue($report['ready']);
        $this->assertSame([], $report['blockers']);
        $this->assertSame(['ITEM_WITHOUT_TRACK'], $this->codes($report['warnings']));
        $this->assertSame(1, $report['info']['total_items']);
        $this->assertSame(0, $report['info']['ready_items']);
        $this->assertSame(1, $report['info']['items_without_current_audio']);
    }

    #[Test]
    public function a_matching_completed_track_makes_the_item_ready(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedAudio($project);
        $this->trackFor($project, $item, ['asset_id' => $asset->id, 'duration_seconds' => 5]);

        $report = $this->service()->evaluate($project, 1);

        $this->assertTrue($report['ready']);
        $this->assertSame([], $report['blockers']);
        $this->assertSame([], $report['warnings']);
        $this->assertSame(1, $report['info']['ready_items']);
        $this->assertSame(1, $report['info']['completed_tracks']);
    }

    #[Test]
    public function a_stale_completed_track_never_counts_as_usable_audio(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $this->trackFor($project, $item, [
            'narration_snapshot' => 'An older narration.',
            'narration_hash' => NarrationHasher::hash('An older narration.'),
        ]);

        $report = $this->service()->evaluate($project, 1);

        $this->assertTrue($report['ready']);
        $this->assertSame([], $report['blockers']);
        $this->assertSame(
            ['ITEM_WITHOUT_TRACK', 'NARRATION_CHANGED_SINCE_SYNTHESIS'],
            $this->codes($report['warnings'])
        );
        $this->assertSame(0, $report['info']['ready_items']);
        $this->assertSame(1, $report['info']['completed_tracks']);
    }

    #[Test]
    public function a_failed_attempt_for_the_current_narration_is_a_blocker(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $this->trackFor($project, $item, ['status' => TtsTrackStatus::Failed, 'error' => 'TTS synthesis failed.']);

        $report = $this->service()->evaluate($project, 1);

        $this->assertFalse($report['ready']);
        $this->assertSame(['TTS_GENERATION_FAILED'], $this->codes($report['blockers']));
        $this->assertSame([], $this->codes($report['warnings']));
    }

    #[Test]
    public function an_old_failed_track_does_not_block_a_matching_completed_track(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedAudio($project);
        $this->trackFor($project, $item, ['status' => TtsTrackStatus::Failed, 'error' => 'TTS synthesis failed.']);
        $this->trackFor($project, $item, ['asset_id' => $asset->id, 'duration_seconds' => 5]);

        $report = $this->service()->evaluate($project, 1);

        $this->assertTrue($report['ready']);
        $this->assertSame([], $report['blockers']);
    }

    #[Test]
    public function the_newest_failed_track_does_not_hide_an_older_matching_completed_track(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedAudio($project);
        $this->trackFor($project, $item, ['asset_id' => $asset->id, 'duration_seconds' => 5]);
        $this->trackFor($project, $item, ['status' => TtsTrackStatus::Failed, 'error' => 'TTS synthesis failed.']);

        $report = $this->service()->evaluate($project, 1);

        $this->assertTrue($report['ready']);
        $this->assertSame([], $report['blockers']);
        $this->assertSame(1, $report['info']['ready_items']);
    }

    #[Test]
    public function archived_tracks_are_never_usable_audio(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $this->trackFor($project, $item, ['status' => TtsTrackStatus::Archived]);

        $report = $this->service()->evaluate($project, 1);

        $this->assertTrue($report['ready']);
        $this->assertSame(['ITEM_WITHOUT_TRACK'], $this->codes($report['warnings']));
        $this->assertSame(0, $report['info']['ready_items']);
        $this->assertSame(0, $report['info']['completed_tracks']);
    }

    #[Test]
    public function a_matching_completed_track_wins_alongside_archived_and_stale_history(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedAudio($project);
        $this->trackFor($project, $item, [
            'status' => TtsTrackStatus::Archived,
            'narration_snapshot' => 'An older narration.',
            'narration_hash' => NarrationHasher::hash('An older narration.'),
        ]);
        $this->trackFor($project, $item, ['asset_id' => $asset->id, 'duration_seconds' => 5]);
        $this->trackFor($project, $item, [
            'status' => TtsTrackStatus::Completed,
            'narration_snapshot' => 'Another older narration.',
            'narration_hash' => NarrationHasher::hash('Another older narration.'),
        ]);

        $report = $this->service()->evaluate($project, 1);

        $this->assertTrue($report['ready']);
        $this->assertSame([], $report['blockers']);
        $this->assertSame([], $report['warnings']);
        $this->assertSame(1, $report['info']['ready_items']);
        $this->assertSame(2, $report['info']['completed_tracks']);
    }

    #[Test]
    public function stalled_archived_history_with_a_different_hash_reports_the_narration_change(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $this->trackFor($project, $item, [
            'status' => TtsTrackStatus::Archived,
            'narration_snapshot' => 'An older narration.',
            'narration_hash' => NarrationHasher::hash('An older narration.'),
        ]);

        $report = $this->service()->evaluate($project, 1);

        $this->assertSame(
            ['ITEM_WITHOUT_TRACK', 'NARRATION_CHANGED_SINCE_SYNTHESIS'],
            $this->codes($report['warnings'])
        );
    }

    #[Test]
    public function an_exact_duration_match_reports_no_mismatch(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan, ['duration_seconds' => 30]);
        $asset = $this->verifiedAudio($project, ['duration_seconds' => 30]);
        $this->trackFor($project, $item, ['asset_id' => $asset->id, 'duration_seconds' => 30]);

        $report = $this->service()->evaluate($project, 1);

        $this->assertTrue($report['ready']);
        $this->assertNotContains('TRACK_DURATION_MISMATCH', $this->codes($report['warnings']));
    }

    #[Test]
    public function a_duration_one_second_below_expected_is_a_mismatch(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan, ['duration_seconds' => 30]);
        $asset = $this->verifiedAudio($project, ['duration_seconds' => 29]);
        $this->trackFor($project, $item, ['asset_id' => $asset->id, 'duration_seconds' => 29]);

        $report = $this->service()->evaluate($project, 1);

        $this->assertContains('TRACK_DURATION_MISMATCH', $this->codes($report['warnings']));
    }

    #[Test]
    public function a_duration_one_second_above_expected_is_a_mismatch(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan, ['duration_seconds' => 30]);
        $asset = $this->verifiedAudio($project, ['duration_seconds' => 31]);
        $this->trackFor($project, $item, ['asset_id' => $asset->id, 'duration_seconds' => 31]);

        $report = $this->service()->evaluate($project, 1);

        $this->assertContains('TRACK_DURATION_MISMATCH', $this->codes($report['warnings']));
    }

    #[Test]
    public function no_mismatch_is_reported_when_the_track_has_no_measured_duration(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan, ['duration_seconds' => 30]);
        $this->trackFor($project, $item, ['duration_seconds' => null]);

        $report = $this->service()->evaluate($project, 1);

        $this->assertContains('METADATA_NOT_VERIFIED', $this->codes($report['warnings']));
        $this->assertNotContains('TRACK_DURATION_MISMATCH', $this->codes($report['warnings']));
    }

    #[Test]
    public function unverified_audio_metadata_is_a_warning(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = Asset::factory()->withUnverifiedFile()->create([
            'content_project_id' => $project->id,
            'type' => AssetType::Audio,
        ]);
        $this->trackFor($project, $item, ['asset_id' => $asset->id]);

        $report = $this->service()->evaluate($project, 1);

        $this->assertTrue($report['ready']);
        $this->assertSame(['METADATA_NOT_VERIFIED'], $this->codes($report['warnings']));
    }

    #[Test]
    public function a_completed_track_without_an_asset_has_unverified_metadata(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan, ['duration_seconds' => 5]);
        $this->trackFor($project, $item, ['duration_seconds' => null]);

        $report = $this->service()->evaluate($project, 1);

        $this->assertSame(['METADATA_NOT_VERIFIED'], $this->codes($report['warnings']));
    }

    #[Test]
    public function a_narration_of_exactly_180_words_is_within_the_guidance(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $this->itemFor($plan, ['narration_text' => implode(' ', array_fill(0, 180, 'word'))]);

        $report = $this->service()->evaluate($project, 1);

        $this->assertNotContains('LONG_NARRATION', $this->codes($report['warnings']));
    }

    #[Test]
    public function a_narration_of_181_words_warns(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $this->itemFor($plan, ['narration_text' => implode(' ', array_fill(0, 181, 'word'))]);

        $report = $this->service()->evaluate($project, 1);

        $this->assertContains('LONG_NARRATION', $this->codes($report['warnings']));
    }

    #[Test]
    public function an_unknown_provider_is_a_blocker_and_is_reported_unavailable(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $this->itemFor($plan);

        config(['tts.default_provider' => 'ghost']);

        $report = $this->service()->evaluate($project, 1);

        $this->assertFalse($report['ready']);
        $this->assertContains('PROVIDER_UNAVAILABLE', $this->codes($report['blockers']));
        $this->assertFalse($report['info']['provider']['available']);
        $this->assertSame('ghost', $report['info']['provider']['name']);
    }

    #[Test]
    public function a_resolvable_provider_is_reported_available(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $this->planFor($project);

        $report = $this->service()->evaluate($project, 1);

        $this->assertTrue($report['info']['provider']['available']);
        $this->assertSame('fake', $report['info']['provider']['name']);
    }

    #[Test]
    public function blockers_and_warnings_together_are_ordered_deterministically(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $this->itemFor($plan, ['order' => 1, 'narration_text' => '']);
        $this->itemFor($plan, ['order' => 2, 'narration_text' => implode(' ', array_fill(0, 181, 'word'))]);
        $this->itemFor($plan, [
            'order' => 3,
            'narration_text' => 'Narration whose generation failed.',
            'duration_seconds' => 5,
        ]);
        $failed = $plan->items()->orderBy('order')->get()->last();
        $this->trackFor($project, $failed, ['status' => TtsTrackStatus::Failed, 'error' => 'TTS synthesis failed.']);

        config(['tts.default_provider' => 'ghost']);

        $first = $this->service()->evaluate($project, 1);
        $second = $this->service()->evaluate($project, 1);

        $this->assertSame($first, $second);
        $this->assertFalse($first['ready']);
        $this->assertSame(
            ['ITEM_WITHOUT_NARRATION', 'TTS_GENERATION_FAILED', 'PROVIDER_UNAVAILABLE'],
            $this->codes($first['blockers'])
        );
        $this->assertSame(
            ['ITEM_WITHOUT_TRACK', 'LONG_NARRATION'],
            $this->codes($first['warnings'])
        );
    }

    #[Test]
    public function another_projects_tracks_do_not_influence_the_report(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $otherProject = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $this->scriptVersionFor($otherProject);
        $plan = $this->planFor($project);
        $this->itemFor($plan);

        $otherPlan = $this->planFor($otherProject);
        $otherItem = $this->itemFor($otherPlan);
        $this->trackFor($otherProject, $otherItem, ['status' => TtsTrackStatus::Completed]);

        $report = $this->service()->evaluate($project, 1);

        $this->assertSame(['ITEM_WITHOUT_TRACK'], $this->codes($report['warnings']));
        $this->assertSame(0, $report['info']['completed_tracks']);
        $this->assertSame(0, $report['info']['ready_items']);
    }

    #[Test]
    public function evaluation_is_read_only_and_repeatable(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedAudio($project);
        $this->trackFor($project, $item, ['asset_id' => $asset->id, 'duration_seconds' => 5]);

        $counts = [
            'tracks' => TtsTrack::count(),
            'assets' => Asset::count(),
            'items' => VisualPlanItem::count(),
            'plans' => VisualPlan::count(),
        ];

        $first = $this->service()->evaluate($project, 1);
        $second = $this->service()->evaluate($project, 1);

        $this->assertSame($first, $second);
        $this->assertSame($counts['tracks'], TtsTrack::count());
        $this->assertSame($counts['assets'], Asset::count());
        $this->assertSame($counts['items'], VisualPlanItem::count());
        $this->assertSame($counts['plans'], VisualPlan::count());
    }

    #[Test]
    public function readiness_never_invokes_synthesis(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $this->itemFor($plan);

        $spy = new class implements TtsProvider
        {
            public bool $synthesized = false;

            public function name(): string
            {
                return 'spy';
            }

            public function synthesize(TtsRequest $request): TtsResult
            {
                $this->synthesized = true;

                return new TtsResult(
                    audio: "\0",
                    durationSeconds: 1,
                    mimeType: 'audio/wav',
                    extension: 'wav',
                    provider: $this->name(),
                    model: null,
                );
            }
        };

        $this->app->instance(TtsProviderRegistry::class, new TtsProviderRegistry(['spy' => $spy]));
        config(['tts.default_provider' => 'spy']);

        $this->service()->evaluate($project, 1);
        $this->service()->evaluate($project, 1);

        $this->assertFalse($spy->synthesized);
    }
}
