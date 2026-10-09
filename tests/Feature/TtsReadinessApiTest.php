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
use App\Services\TTS\NarrationHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TtsReadinessApiTest extends TestCase
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
            ->getJson('/api/v1/projects/9999/script/versions/1/visual-plan/tts-readiness')
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
    public function any_authenticated_user_can_request_the_report_under_the_existing_policy_convention(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $project = $this->projectFor($owner);
        $plan = $this->planFor($project);
        $this->itemFor($plan);

        $this->actingAs($viewer, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk();
    }

    #[Test]
    public function a_missing_plan_returns_a_successful_report_with_a_blocker(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptVersionFor($project);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ready', false)
            ->assertJsonPath('data.info.total_items', 0);

        $codes = array_column($response->json('data.blockers'), 'code');
        $this->assertContains('NO_VISUAL_PLAN', $codes);
    }

    #[Test]
    public function an_empty_plan_returns_a_successful_report_with_a_blocker(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);

        $this->assertSame(0, $plan->items()->count());

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk()
            ->assertJsonPath('data.ready', false);

        $codes = array_column($response->json('data.blockers'), 'code');
        $this->assertContains('NO_VISUAL_PLAN_ITEMS', $codes);
    }

    #[Test]
    public function it_returns_the_readiness_structure_for_a_ready_version(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedAudio($project);
        $this->trackFor($project, $item, ['asset_id' => $asset->id, 'duration_seconds' => 5]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk()
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.blockers', [])
            ->assertJsonPath('data.warnings', [])
            ->assertJsonPath('data.info.total_items', 1)
            ->assertJsonPath('data.info.ready_items', 1)
            ->assertJsonPath('data.info.items_without_current_audio', 0)
            ->assertJsonPath('data.info.completed_tracks', 1)
            ->assertJsonPath('data.info.provider.available', true);

        $response->assertJsonStructure([
            'success',
            'data' => [
                'ready',
                'blockers',
                'warnings',
                'info' => [
                    'total_items',
                    'ready_items',
                    'items_without_current_audio',
                    'completed_tracks',
                    'provider' => ['name', 'available'],
                ],
            ],
        ]);
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
            ->assertJsonPath('data.ready', false)
            ->assertJsonPath('data.info.provider.available', false);

        $codes = array_column($response->json('data.blockers'), 'code');
        $this->assertContains('PROVIDER_UNAVAILABLE', $codes);
    }

    #[Test]
    public function the_report_never_exposes_private_asset_data(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedAudio($project, [
            'file_path' => 'projects/secret-storage/inner-voice.mp3',
            'file_name' => 'inner-voice.mp3',
        ]);
        $this->trackFor($project, $item, [
            'asset_id' => $asset->id,
            'duration_seconds' => 5,
            'status' => TtsTrackStatus::Completed,
        ]);

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
    public function the_endpoint_is_read_only_and_repeatable(): void
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

        $first = $this->actingAs($user, 'sanctum')->getJson($this->url($project))->assertOk();
        $second = $this->actingAs($user, 'sanctum')->getJson($this->url($project))->assertOk();

        $this->assertSame($first->json('data'), $second->json('data'));
        $this->assertSame($counts['tracks'], TtsTrack::count());
        $this->assertSame($counts['assets'], Asset::count());
        $this->assertSame($counts['items'], VisualPlanItem::count());
        $this->assertSame($counts['plans'], VisualPlan::count());
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function url(ContentProject $project, int $version = 1): string
    {
        return "/api/v1/projects/{$project->id}/script/versions/{$version}/visual-plan/tts-readiness";
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
}
