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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TtsManifestApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_endpoint_requires_authentication(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->planFor($project);

        $this->getJson($this->url($project))->assertUnauthorized();
    }

    #[Test]
    public function the_endpoint_returns_404_for_a_missing_project(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/projects/9999/script/versions/1/visual-plan/tts-manifest')
            ->assertNotFound();
    }

    #[Test]
    public function a_version_from_another_project_returns_404(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $otherProject = $this->projectFor($user);
        $this->scriptVersionFor($otherProject);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertNotFound();
    }

    #[Test]
    public function any_authenticated_user_can_request_the_manifest_under_the_existing_policy_convention(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $project = $this->projectFor($owner);
        $this->planFor($project);

        $this->actingAs($viewer, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk();
    }

    #[Test]
    public function a_missing_plan_returns_a_successful_manifest_with_a_blocker(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $version = $this->scriptVersionFor($project);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ready', false)
            ->assertJsonPath('data.visual_plan_id', null)
            ->assertJsonPath('data.script_version_id', $version->getKey())
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.info.total_items', 0);

        $codes = array_column($response->json('data.blockers'), 'code');
        $this->assertContains('NO_VISUAL_PLAN', $codes);
    }

    #[Test]
    public function an_empty_plan_returns_a_manifest_with_a_blocker(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk()
            ->assertJsonPath('data.ready', false)
            ->assertJsonPath('data.visual_plan_id', $plan->getKey());

        $codes = array_column($response->json('data.blockers'), 'code');
        $this->assertContains('NO_VISUAL_PLAN_ITEMS', $codes);
    }

    #[Test]
    public function a_ready_version_returns_the_full_manifest_structure(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedAudio($project);
        $track = $this->trackFor($project, $item, ['asset_id' => $asset->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk()
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.script_version_id', $plan->script_version_id)
            ->assertJsonPath('data.visual_plan_id', $plan->getKey())
            ->assertJsonPath('data.blockers', [])
            ->assertJsonPath('data.warnings', [])
            ->assertJsonPath('data.info.total_items', 1)
            ->assertJsonPath('data.info.items_with_audio', 1)
            ->assertJsonPath('data.info.items_without_audio', 0)
            ->assertJsonPath('data.items.0.order', 1)
            ->assertJsonPath('data.items.0.visual_plan_item_id', $item->getKey())
            ->assertJsonPath('data.items.0.audio.available', true)
            ->assertJsonPath('data.items.0.audio.tts_track_id', $track->getKey())
            ->assertJsonPath('data.items.0.audio.asset_id', $asset->getKey())
            ->assertJsonPath('data.items.0.audio.duration_seconds', 5);
    }

    #[Test]
    public function an_unknown_provider_configuration_returns_a_blocker_instead_of_a_500(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->planFor($project);

        config(['tts.default_provider' => 'ghost']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk()
            ->assertJsonPath('data.ready', false);

        $codes = array_column($response->json('data.blockers'), 'code');
        $this->assertContains('PROVIDER_UNAVAILABLE', $codes);
    }

    #[Test]
    public function the_manifest_never_leaks_private_asset_data(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedAudio($project, [
            'file_path' => 'projects/secret-storage/inner-voice.mp3',
            'file_name' => 'inner-voice.mp3',
        ]);
        $this->trackFor($project, $item, ['asset_id' => $asset->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk();

        $content = $response->getContent();
        $this->assertStringNotContainsString('file_path', $content);
        $this->assertStringNotContainsString('file_name', $content);
        $this->assertStringNotContainsString('storage_path', $content);
        $this->assertStringNotContainsString('storage/', $content);
        $this->assertStringNotContainsString('secret-storage', $content);
        $this->assertStringNotContainsString('inner-voice.mp3', $content);
    }

    #[Test]
    public function stale_audio_is_never_reported_as_current(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $this->trackFor($project, $item, [
            'narration_snapshot' => 'An older version of the narration.',
            'narration_hash' => NarrationHasher::hash('An older version of the narration.'),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk()
            ->assertJsonPath('data.items.0.audio.available', false)
            ->assertJsonPath('data.info.items_without_audio', 1);

        $manifestWarnings = array_column($response->json('data.items.0.warnings'), 'code');
        $this->assertContains('NARRATION_CHANGED_SINCE_SYNTHESIS', $manifestWarnings);
    }

    #[Test]
    public function manifest_and_readiness_agree_for_the_same_state(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $usableItem = $this->itemFor($plan, ['order' => 1, 'narration_text' => 'First narration.']);
        $this->itemFor($plan, ['order' => 2, 'narration_text' => 'Second narration.']);
        $asset = $this->verifiedAudio($project);
        $this->trackFor($project, $usableItem, ['asset_id' => $asset->id, 'duration_seconds' => 5]);

        $manifest = $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project, 'tts-manifest'))
            ->assertOk()
            ->json('data');

        $readiness = $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project, 'tts-readiness'))
            ->assertOk()
            ->json('data');

        $this->assertSame($readiness['ready'], $manifest['ready']);
        $this->assertSame(
            array_column($readiness['blockers'], 'code'),
            array_column($manifest['blockers'], 'code')
        );
        $this->assertSame(
            array_column($readiness['warnings'], 'code'),
            array_column($manifest['warnings'], 'code')
        );
        $this->assertSame($readiness['info']['ready_items'], $manifest['info']['items_with_audio']);
        $this->assertSame($readiness['info']['items_without_current_audio'], $manifest['info']['items_without_audio']);
    }

    #[Test]
    public function the_manifest_is_scoped_to_the_requested_projects_own_version(): void
    {
        $user = User::factory()->create();
        $otherProject = $this->projectFor($user);
        $otherPlan = $this->planFor($otherProject);
        $otherItem = $this->itemFor($otherPlan);

        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk()
            ->assertJsonPath('data.visual_plan_id', $plan->getKey())
            ->assertJsonPath('data.items.0.visual_plan_item_id', $item->getKey())
            ->assertJsonPath('data.items.0.audio.available', false);
    }

    #[Test]
    public function another_projects_assets_and_tracks_are_not_substituted(): void
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

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk()
            ->assertJsonPath('data.items.0.audio.available', false);

        $content = $response->getContent();
        $this->assertStringNotContainsString('"tts_track_id":'.$otherTrack->getKey(), $content);
        $this->assertStringNotContainsString('"asset_id":'.$otherAsset->getKey(), $content);
    }

    #[Test]
    public function the_manifest_is_read_only_and_repeatable(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedAudio($project);
        $this->trackFor($project, $item, ['asset_id' => $asset->id]);

        $disk = Storage::disk((string) config('assets.disk'));
        $filesBefore = collect($disk->allFiles())->sort()->values()->all();
        $assetBefore = $asset->fresh();

        $counts = [
            'tracks' => TtsTrack::count(),
            'assets' => Asset::count(),
            'items' => VisualPlanItem::count(),
            'plans' => VisualPlan::count(),
        ];

        $first = $this->actingAs($user, 'sanctum')->getJson($this->url($project))->assertOk();
        $second = $this->actingAs($user, 'sanctum')->getJson($this->url($project))->assertOk();
        $third = $this->actingAs($user, 'sanctum')->getJson($this->url($project))->assertOk();

        $this->assertSame($first->json('data'), $second->json('data'));
        $this->assertSame($second->json('data'), $third->json('data'));
        $this->assertSame($counts['tracks'], TtsTrack::count());
        $this->assertSame($counts['assets'], Asset::count());
        $this->assertSame($counts['items'], VisualPlanItem::count());
        $this->assertSame($counts['plans'], VisualPlan::count());

        $this->assertSame($filesBefore, collect($disk->allFiles())->sort()->values()->all());
        $assetAfter = $asset->fresh();
        $this->assertSame($assetBefore->duration_seconds, $assetAfter->duration_seconds);
        $this->assertSame($assetBefore->metadata_status, $assetAfter->metadata_status);
        $this->assertSame($assetBefore->metadata_error, $assetAfter->metadata_error);
        $this->assertSame($assetBefore->updated_at->toDateTimeString(), $assetAfter->updated_at->toDateTimeString());
    }

    #[Test]
    public function the_manifest_never_invokes_synthesis(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedAudio($project);
        $this->trackFor($project, $item, ['asset_id' => $asset->id]);

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

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk()
            ->assertJsonPath('data.ready', true);

        $this->assertFalse($spy->synthesized);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function url(ContentProject $project, string $endpoint = 'tts-manifest', int $version = 1): string
    {
        return "/api/v1/projects/{$project->id}/script/versions/{$version}/visual-plan/{$endpoint}";
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
}
