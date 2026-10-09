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
use App\Services\ScriptService;
use App\Services\TTS\NarrationHasher;
use App\Services\TTS\TtsManifestService;
use App\Services\TTS\TtsProviderRegistry;
use App\Services\TTS\TtsReadinessService;
use App\Services\VisualPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TtsManifestServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TtsManifestService
    {
        return new TtsManifestService(
            app(TtsReadinessService::class),
            app(ScriptService::class)
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
            'mime_type' => 'audio/wav',
            'duration_seconds' => 5,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function unverifiedAudio(ContentProject $project, array $overrides = []): Asset
    {
        return Asset::factory()->withUnverifiedFile()->create(array_merge([
            'content_project_id' => $project->id,
            'type' => AssetType::Audio,
            'mime_type' => 'audio/wav',
        ], $overrides));
    }

    /**
     * @param  array<int, array<string, mixed>>  $issues
     * @return array<int, string>
     */
    private function codes(array $issues): array
    {
        return array_column($issues, 'code');
    }

    private function itemAt(array $manifest, int $index): array
    {
        return $manifest['items'][$index];
    }

    #[Test]
    public function a_missing_visual_plan_is_an_empty_manifest_with_a_blocker(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $version = $this->scriptVersionFor($project);

        $manifest = $this->service()->build($project, 1);

        $this->assertFalse($manifest['ready']);
        $this->assertSame(['NO_VISUAL_PLAN'], $this->codes($manifest['blockers']));
        $this->assertSame([], $manifest['warnings']);
        $this->assertNull($manifest['visual_plan_id']);
        $this->assertSame($version->getKey(), $manifest['script_version_id']);
        $this->assertSame([], $manifest['items']);
        $this->assertSame(['total_items' => 0, 'items_with_audio' => 0, 'items_without_audio' => 0], $manifest['info']);
    }

    #[Test]
    public function an_empty_visual_plan_is_a_blocker_without_items(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);

        $manifest = $this->service()->build($project, 1);

        $this->assertFalse($manifest['ready']);
        $this->assertSame(['NO_VISUAL_PLAN_ITEMS'], $this->codes($manifest['blockers']));
        $this->assertSame($plan->getKey(), $manifest['visual_plan_id']);
        $this->assertSame($plan->script_version_id, $manifest['script_version_id']);
        $this->assertSame([], $manifest['items']);
    }

    #[Test]
    public function an_item_without_any_track_is_not_available_with_a_warning(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $this->itemFor($plan);

        $manifest = $this->service()->build($project, 1);

        $item = $this->itemAt($manifest, 0);

        $this->assertTrue($manifest['ready']);
        $this->assertFalse($item['audio']['available']);
        $this->assertSame(['ITEM_WITHOUT_TRACK'], $this->codes($item['warnings']));
        $this->assertSame([], $item['blockers']);
        $this->assertSame(NarrationHasher::hash('Cats purr to communicate with humans.'), $item['narration']['hash']);
        $this->assertSame(['total_items' => 1, 'items_with_audio' => 0, 'items_without_audio' => 1], $manifest['info']);
    }

    #[Test]
    public function a_matching_completed_track_is_selected_with_verified_metadata(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedAudio($project);
        $track = $this->trackFor($project, $item, [
            'asset_id' => $asset->id,
            'provider' => 'elevenlabs',
            'model' => 'eleven-multilingual-v2',
            'voice' => 'nova',
            'language' => 'en',
            'speed' => 1.25,
        ]);

        $manifest = $this->service()->build($project, 1);

        $itemEntry = $this->itemAt($manifest, 0);

        $this->assertTrue($manifest['ready']);
        $this->assertTrue($itemEntry['audio']['available']);
        $this->assertSame($item->getKey(), $itemEntry['visual_plan_item_id']);
        $this->assertSame(1, $itemEntry['order']);
        $this->assertSame($track->getKey(), $itemEntry['audio']['tts_track_id']);
        $this->assertSame($asset->getKey(), $itemEntry['audio']['asset_id']);
        $this->assertSame(5, $itemEntry['audio']['duration_seconds']);
        $this->assertSame('audio/wav', $itemEntry['audio']['mime_type']);
        $this->assertSame('elevenlabs', $itemEntry['audio']['provider']);
        $this->assertSame('eleven-multilingual-v2', $itemEntry['audio']['model']);
        $this->assertSame('nova', $itemEntry['audio']['voice']);
        $this->assertSame('en', $itemEntry['audio']['language']);
        $this->assertSame(1.25, $itemEntry['audio']['speed']);
        $this->assertSame(NarrationHasher::hash($item->narration_text), $itemEntry['narration']['hash']);
        $this->assertSame([], $this->codes($itemEntry['warnings']));
        $this->assertSame(['total_items' => 1, 'items_with_audio' => 1, 'items_without_audio' => 0], $manifest['info']);
    }

    #[Test]
    public function a_stale_completed_track_is_never_selected(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $this->trackFor($project, $item, [
            'narration_snapshot' => 'An older version of this narration.',
            'narration_hash' => NarrationHasher::hash('An older version of this narration.'),
        ]);

        $manifest = $this->service()->build($project, 1);

        $itemEntry = $this->itemAt($manifest, 0);

        $this->assertFalse($itemEntry['audio']['available']);
        $this->assertSame(['ITEM_WITHOUT_TRACK', 'NARRATION_CHANGED_SINCE_SYNTHESIS'], $this->codes($itemEntry['warnings']));
        $this->assertSame(['total_items' => 1, 'items_with_audio' => 0, 'items_without_audio' => 1], $manifest['info']);
    }

    #[Test]
    public function a_matching_completed_track_stays_current_when_a_newer_track_changed_narration(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedAudio($project);
        $current = $this->trackFor($project, $item, ['asset_id' => $asset->id]);
        $this->trackFor($project, $item, [
            'asset_id' => null,
            'narration_snapshot' => 'Narration that was changed afterwards.',
            'narration_hash' => NarrationHasher::hash('Narration that was changed afterwards.'),
        ]);

        $manifest = $this->service()->build($project, 1);
        $itemEntry = $this->itemAt($manifest, 0);

        $this->assertTrue($itemEntry['audio']['available']);
        $this->assertSame($current->getKey(), $itemEntry['audio']['tts_track_id']);
        $this->assertNotContains('NARRATION_CHANGED_SINCE_SYNTHESIS', $this->codes($itemEntry['warnings']));
        $this->assertSame(['total_items' => 1, 'items_with_audio' => 1, 'items_without_audio' => 0], $manifest['info']);
    }

    #[Test]
    public function the_newest_failed_track_does_not_hide_an_older_matching_completed_track(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedAudio($project);
        $completed = $this->trackFor($project, $item, ['asset_id' => $asset->id]);
        $this->trackFor($project, $item, [
            'status' => TtsTrackStatus::Failed,
            'error' => 'tone',
        ]);

        $manifest = $this->service()->build($project, 1);
        $itemEntry = $this->itemAt($manifest, 0);

        $this->assertTrue($manifest['ready']);
        $this->assertTrue($itemEntry['audio']['available']);
        $this->assertSame($completed->getKey(), $itemEntry['audio']['tts_track_id']);
    }

    #[Test]
    public function a_failed_current_attempt_is_a_blocker_and_not_available(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $this->trackFor($project, $item, [
            'status' => TtsTrackStatus::Failed,
            'error' => 'tone',
        ]);

        $manifest = $this->service()->build($project, 1);
        $itemEntry = $this->itemAt($manifest, 0);

        $this->assertFalse($manifest['ready']);
        $this->assertFalse($itemEntry['audio']['available']);
        $this->assertSame(['TTS_GENERATION_FAILED'], $this->codes($itemEntry['blockers']));
        $this->assertSame(['total_items' => 1, 'items_with_audio' => 0, 'items_without_audio' => 1], $manifest['info']);
    }

    #[Test]
    public function only_archived_tracks_are_never_available(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $this->trackFor($project, $item, [
            'status' => TtsTrackStatus::Archived,
        ]);

        $manifest = $this->service()->build($project, 1);
        $itemEntry = $this->itemAt($manifest, 0);

        $this->assertFalse($itemEntry['audio']['available']);
        $this->assertSame(['ITEM_WITHOUT_TRACK'], $this->codes($itemEntry['warnings']));
        $this->assertNotContains('NARRATION_CHANGED_SINCE_SYNTHESIS', $this->codes($itemEntry['warnings']));
        $this->assertSame(['total_items' => 1, 'items_with_audio' => 0, 'items_without_audio' => 1], $manifest['info']);
    }

    #[Test]
    public function a_matching_completed_track_wins_over_archived_history(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedAudio($project);
        $completed = $this->trackFor($project, $item, ['asset_id' => $asset->id]);
        $this->trackFor($project, $item, [
            'status' => TtsTrackStatus::Archived,
        ]);

        $manifest = $this->service()->build($project, 1);
        $itemEntry = $this->itemAt($manifest, 0);

        $this->assertTrue($itemEntry['audio']['available']);
        $this->assertSame($completed->getKey(), $itemEntry['audio']['tts_track_id']);
        $this->assertSame(['total_items' => 1, 'items_with_audio' => 1, 'items_without_audio' => 0], $manifest['info']);
    }

    #[Test]
    public function when_multiple_completed_tracks_match_the_newest_is_selected(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedAudio($project);
        $older = $this->trackFor($project, $item);
        $newest = $this->trackFor($project, $item, ['asset_id' => $asset->id]);

        $manifest = $this->service()->build($project, 1);
        $itemEntry = $this->itemAt($manifest, 0);

        $this->assertTrue($itemEntry['audio']['available']);
        $this->assertSame($newest->getKey(), $itemEntry['audio']['tts_track_id']);
        $this->assertNotSame($older->getKey(), $itemEntry['audio']['tts_track_id']);
    }

    #[Test]
    public function a_completed_track_missing_its_asset_has_unverified_metadata(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $this->trackFor($project, $item, ['asset_id' => null]);

        $manifest = $this->service()->build($project, 1);
        $itemEntry = $this->itemAt($manifest, 0);

        $this->assertTrue($itemEntry['audio']['available']);
        $this->assertSame(['METADATA_NOT_VERIFIED'], $this->codes($itemEntry['warnings']));
        $this->assertNull($itemEntry['audio']['duration_seconds']);
        $this->assertNull($itemEntry['audio']['mime_type']);
        $this->assertSame(['total_items' => 1, 'items_with_audio' => 1, 'items_without_audio' => 0], $manifest['info']);
    }

    #[Test]
    public function an_unverified_asset_keeps_verified_fields_null(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->unverifiedAudio($project, ['duration_seconds' => 5]);
        $this->trackFor($project, $item, ['asset_id' => $asset->id, 'duration_seconds' => 5]);

        $manifest = $this->service()->build($project, 1);
        $itemEntry = $this->itemAt($manifest, 0);

        $this->assertTrue($itemEntry['audio']['available']);
        $this->assertSame(['METADATA_NOT_VERIFIED'], $this->codes($itemEntry['warnings']));
        $this->assertNull($itemEntry['audio']['duration_seconds']);
        $this->assertNull($itemEntry['audio']['mime_type']);
    }

    #[Test]
    public function an_exact_duration_match_adds_no_mismatch_warning(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan, ['duration_seconds' => 5]);
        $this->trackFor($project, $item, ['duration_seconds' => 5]);

        $manifest = $this->service()->build($project, 1);
        $itemEntry = $this->itemAt($manifest, 0);

        $this->assertNotEmpty($itemEntry['audio']['tts_track_id']);
        $this->assertNotContains('TRACK_DURATION_MISMATCH', $this->codes($itemEntry['warnings']));
    }

    #[Test]
    public function a_duration_mismatch_is_warned_without_blocking(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan, ['duration_seconds' => 5]);
        $asset = $this->verifiedAudio($project, ['duration_seconds' => 5]);
        $this->trackFor($project, $item, ['asset_id' => $asset->id, 'duration_seconds' => 30]);

        $manifest = $this->service()->build($project, 1);
        $itemEntry = $this->itemAt($manifest, 0);

        $this->assertTrue($itemEntry['audio']['available']);
        $this->assertSame(['TRACK_DURATION_MISMATCH'], $this->codes($itemEntry['warnings']));
        $this->assertTrue($manifest['ready']);
    }

    #[Test]
    public function a_long_narration_is_warned_without_blocking(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $narration = implode(' ', array_fill(0, 181, 'word'));
        $item = $this->itemFor($plan, ['narration_text' => $narration]);
        $asset = $this->verifiedAudio($project);
        $this->trackFor($project, $item, ['asset_id' => $asset->id]);

        $manifest = $this->service()->build($project, 1);
        $itemEntry = $this->itemAt($manifest, 0);

        $this->assertTrue($itemEntry['audio']['available']);
        $this->assertSame(['LONG_NARRATION'], $this->codes($itemEntry['warnings']));
        $this->assertTrue($manifest['ready']);
    }

    #[Test]
    public function an_item_without_usable_narration_is_a_blocker(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $this->itemFor($plan, ['narration_text' => "   \n"]);

        $manifest = $this->service()->build($project, 1);
        $itemEntry = $this->itemAt($manifest, 0);

        $this->assertFalse($manifest['ready']);
        $this->assertFalse($itemEntry['audio']['available']);
        $this->assertSame(['ITEM_WITHOUT_NARRATION'], $this->codes($itemEntry['blockers']));
        $this->assertArrayHasKey('hash', $itemEntry['narration']);
    }

    #[Test]
    public function items_are_ordered_by_the_explicit_plan_order(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $this->itemFor($plan, ['order' => 2, 'narration_text' => 'Second narration.']);
        $this->itemFor($plan, ['order' => 3, 'narration_text' => 'Third narration.']);
        $this->itemFor($plan, ['order' => 1, 'narration_text' => 'First narration.']);

        $manifest = $this->service()->build($project, 1);

        $this->assertSame([1, 2, 3], array_column($manifest['items'], 'order'));
        $this->assertSame(NarrationHasher::hash('First narration.'), $manifest['items'][0]['narration']['hash']);
    }

    #[Test]
    public function mixed_readiness_items_have_correct_info_counts(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $usableItem = $this->itemFor($plan, ['order' => 1, 'narration_text' => 'First narration.']);
        $missingItem = $this->itemFor($plan, ['order' => 2, 'narration_text' => 'Second narration.']);
        $this->itemFor($plan, ['order' => 3, 'narration_text' => 'Third narration.']);
        $asset = $this->verifiedAudio($project);
        $this->trackFor($project, $usableItem, ['asset_id' => $asset->id]);
        $this->trackFor($project, $missingItem, [
            'status' => TtsTrackStatus::Failed,
            'error' => 'tone',
        ]);

        $manifest = $this->service()->build($project, 1);

        $this->assertFalse($manifest['ready']);
        $this->assertSame(3, count($manifest['items']));
        $this->assertSame(1, $manifest['info']['items_with_audio']);
        $this->assertSame(2, $manifest['info']['items_without_audio']);
        $this->assertSame(['TTS_GENERATION_FAILED'], $this->codes($manifest['blockers']));
    }

    #[Test]
    public function an_unknown_provider_blocks_the_manifest_but_keeps_item_segments(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $track = $this->trackFor($project, $item);
        config(['tts.default_provider' => 'does-not-exist']);

        $manifest = $this->service()->build($project, 1);
        $itemEntry = $this->itemAt($manifest, 0);

        $this->assertFalse($manifest['ready']);
        $this->assertSame(['PROVIDER_UNAVAILABLE'], $this->codes($manifest['blockers']));
        $this->assertTrue($itemEntry['audio']['available']);
        $this->assertSame($track->getKey(), $itemEntry['audio']['tts_track_id']);
    }

    #[Test]
    public function another_projects_tracks_do_not_influence_the_manifest(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $otherProject = $this->projectFor($user);
        $plan = $this->planFor($project);
        $otherPlan = $this->planFor($otherProject);
        $this->itemFor($plan);
        $otherItem = $this->itemFor($otherPlan);
        $otherAsset = $this->verifiedAudio($otherProject);
        $otherTrack = $this->trackFor($otherProject, $otherItem, ['asset_id' => $otherAsset->id]);

        $manifest = $this->service()->build($project, 1);
        $itemEntry = $this->itemAt($manifest, 0);

        $this->assertFalse($itemEntry['audio']['available']);
        $this->assertSame(['total_items' => 1, 'items_with_audio' => 0, 'items_without_audio' => 1], $manifest['info']);

        $json = json_encode($manifest);
        $this->assertIsString($json);
        $this->assertStringNotContainsString('"tts_track_id":'.$otherTrack->getKey(), $json);
        $this->assertStringNotContainsString('"asset_id":'.$otherAsset->getKey(), $json);
    }

    #[Test]
    public function the_manifest_agrees_with_readiness_for_the_same_state(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $usableItem = $this->itemFor($plan, ['order' => 1, 'narration_text' => 'First narration.']);
        $missingItem = $this->itemFor($plan, ['order' => 2, 'narration_text' => 'Second narration.']);
        $asset = $this->verifiedAudio($project);
        $this->trackFor($project, $usableItem, ['asset_id' => $asset->id, 'duration_seconds' => 5]);
        $this->trackFor($project, $missingItem, [
            'status' => TtsTrackStatus::Failed,
            'error' => 'tone',
        ]);

        $manifest = $this->service()->build($project, 1);
        $readiness = (new TtsReadinessService(
            app(VisualPlanService::class),
            app(TtsProviderRegistry::class)
        ))->evaluate($project, 1);

        $this->assertSame($readiness['ready'], $manifest['ready']);
        $this->assertSame($this->codes($readiness['blockers']), $this->codes($manifest['blockers']));
        $this->assertSame($this->codes($readiness['warnings']), $this->codes($manifest['warnings']));
        $this->assertSame($readiness['info']['ready_items'], $manifest['info']['items_with_audio']);
        $this->assertSame($readiness['info']['items_without_current_audio'], $manifest['info']['items_without_audio']);
    }

    #[Test]
    public function the_manifest_is_deterministic_read_only_and_never_synthesizes(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $this->trackFor($project, $item);

        $counts = [
            'tracks' => TtsTrack::count(),
            'assets' => Asset::count(),
            'items' => VisualPlanItem::count(),
            'plans' => VisualPlan::count(),
        ];

        $first = $this->service()->build($project, 1);
        $second = $this->service()->build($project, 1);

        $this->assertSame($first, $second);
        $this->assertSame($counts['tracks'], TtsTrack::count());
        $this->assertSame($counts['assets'], Asset::count());
        $this->assertSame($counts['items'], VisualPlanItem::count());
        $this->assertSame($counts['plans'], VisualPlan::count());
    }
}
