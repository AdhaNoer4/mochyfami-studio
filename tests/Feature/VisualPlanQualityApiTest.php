<?php

namespace Tests\Feature;

use App\Enums\AssetRequirementStatus;
use App\Enums\AssetRequirementType;
use App\Enums\ScriptStatus;
use App\Enums\VisualPlanItemType;
use App\Enums\VisualPlanStatus;
use App\Models\ContentProject;
use App\Models\Script;
use App\Models\User;
use App\Models\VisualPlan;
use App\Models\VisualPlanItem;
use App\Services\VisualPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VisualPlanQualityApiTest extends TestCase
{
    use RefreshDatabase;

    private function projectFor(User $user): ContentProject
    {
        return ContentProject::factory()->for($user, 'creator')->create();
    }

    private function scriptFor(ContentProject $project, int $version = 1): Script
    {
        $script = $project->script()->first() ?? Script::factory()->create([
            'content_project_id' => $project->id,
        ]);

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

        return $script;
    }

    private function planFor(ContentProject $project, int $version = 1, array $state = []): VisualPlan
    {
        $script = $this->scriptFor($project, $version);

        return VisualPlan::factory()->create(array_merge([
            'content_project_id' => $project->id,
            'script_version_id' => $script->currentVersion->id,
            'status' => VisualPlanStatus::Draft,
        ], $state));
    }

    private function itemFor(VisualPlan $plan, array $state = []): VisualPlanItem
    {
        static $order = 0;
        $order++;

        return $plan->items()->create(array_merge([
            'order' => $order,
            'section' => 'body',
            'narration_text' => 'Cats purr to communicate with humans.',
            'visual_type' => VisualPlanItemType::AnimalClip,
            'visual_prompt' => 'Close up of a cat purring on a blanket.',
            'duration_seconds' => 5,
            'notes' => null,
        ], $state));
    }

    private function readyPlan(ContentProject $project, int $version = 1): VisualPlan
    {
        $plan = $this->planFor($project, $version, ['status' => VisualPlanStatus::Approved]);
        $item = $this->itemFor($plan);
        $item->assetRequirements()->create([
            'requirement_type' => AssetRequirementType::Video,
            'search_query' => 'cat purring close up',
            'description' => 'A cat purring on a blanket.',
            'target_duration_seconds' => 5,
            'aspect_ratio' => '9:16',
            'status' => AssetRequirementStatus::Pending,
            'notes' => null,
        ]);

        return $plan;
    }

    private function url(ContentProject $project, int $version = 1): string
    {
        return "/api/v1/projects/{$project->id}/script/versions/{$version}/visual-plan/quality";
    }

    #[Test]
    public function test_show_requires_authentication(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->readyPlan($project);

        $this->getJson($this->url($project))->assertUnauthorized();
    }

    #[Test]
    public function test_show_returns_the_documented_payload(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->readyPlan($project);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'visual_plan_id',
                    'script_version_id',
                    'status',
                    'ready',
                    'score',
                    'summary' => [
                        'total_items',
                        'items_with_requirements',
                        'items_without_requirements',
                        'total_requirements',
                        'valid_requirements',
                        'invalid_requirements',
                        'pending_requirements',
                        'searching_requirements',
                        'fulfilled_requirements',
                        'skipped_requirements',
                        'blocker_count',
                        'warning_count',
                    ],
                    'blockers',
                    'warnings',
                    'info',
                ],
            ]);
    }

    #[Test]
    public function test_show_reports_a_ready_plan(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->readyPlan($project);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk()
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.score', 100)
            ->assertJsonPath('data.visual_plan_id', $plan->id)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.blockers', [])
            ->assertJsonPath('data.warnings', [])
            ->assertJsonCount(1, 'data.info');
    }

    #[Test]
    public function test_show_reports_blockers_with_their_codes_and_target_ids(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk()
            ->assertJsonPath('data.ready', false)
            ->assertJsonPath('data.blockers.0.code', 'ITEM_WITHOUT_ASSET_REQUIREMENT')
            ->assertJsonPath('data.blockers.0.severity', 'blocker')
            ->assertJsonPath('data.blockers.0.visual_plan_item_id', $item->id);

        $this->assertSame($plan->id, $response->json('data.visual_plan_id'));
    }

    #[Test]
    public function test_show_returns_404_when_the_visual_plan_does_not_exist(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptFor($project);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function test_show_returns_404_when_the_script_version_does_not_exist(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->scriptFor($project);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project, 9))
            ->assertNotFound();
    }

    #[Test]
    public function test_show_returns_404_for_a_visual_plan_of_another_project(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $otherProject = $this->projectFor($user);
        $this->readyPlan($otherProject);
        $this->scriptFor($project);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertNotFound();
    }

    #[Test]
    public function test_show_returns_404_when_the_project_does_not_exist(): void
    {
        Http::preventStrayRequests();
        User::factory()->create();

        $this->actingAs(User::first(), 'sanctum')
            ->getJson('/api/v1/projects/9999/script/versions/1/visual-plan/quality')
            ->assertNotFound();
    }

    #[Test]
    public function test_show_reports_an_archived_plan_as_not_ready(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->planFor($project, 1, ['status' => VisualPlanStatus::Archived]);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk()
            ->assertJsonPath('data.ready', false)
            ->assertJsonFragment(['code' => 'ARCHIVED_VISUAL_PLAN', 'severity' => 'blocker']);
    }

    #[Test]
    public function test_show_reports_an_empty_plan_as_not_ready(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->planFor($project);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk()
            ->assertJsonPath('data.ready', false)
            ->assertJsonPath('data.summary.total_items', 0)
            ->assertJsonFragment(['code' => 'EMPTY_VISUAL_PLAN']);
    }

    #[Test]
    public function test_show_reports_warnings_without_turning_off_ready(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->readyPlan($project);
        $plan->items()->first()->assetRequirements()->first()->update([
            'search_query' => null,
            'target_duration_seconds' => null,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk()
            ->assertJsonPath('data.ready', true)
            ->assertJsonFragment(['code' => 'MISSING_ASSET_SEARCH_QUERY', 'severity' => 'warning'])
            ->assertJsonFragment(['code' => 'MISSING_TARGET_DURATION', 'severity' => 'warning']);
    }

    #[Test]
    public function test_show_is_read_only_for_the_visual_plan(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project, 1, ['status' => VisualPlanStatus::Review]);
        $before = $plan->getRawOriginal();

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk();

        $this->assertEquals($before, $plan->fresh()->getRawOriginal());
    }

    #[Test]
    public function test_show_is_read_only_for_asset_requirements(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->readyPlan($project);
        $requirement = $plan->items()->first()->assetRequirements()->first();
        $before = $requirement->getRawOriginal();

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk();

        $this->assertEquals($before, $requirement->fresh()->getRawOriginal());
    }

    #[Test]
    public function test_show_makes_no_outbound_request(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->readyPlan($project);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk();

        $this->assertTrue(true, 'No outbound request was attempted.');
    }

    #[Test]
    public function test_show_survives_a_corrupt_stored_plan_status(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->readyPlan($project);

        DB::table('visual_plans')->where('id', $plan->id)->update(['status' => 'limbo']);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk()
            ->assertJsonPath('data.status', 'limbo')
            ->assertJsonPath('data.ready', true);
    }

    #[Test]
    public function test_show_accepts_a_fully_blocked_plan(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project, 1, ['status' => VisualPlanStatus::Archived]);
        $item = $this->itemFor($plan);
        $item->assetRequirements()->create([
            'requirement_type' => AssetRequirementType::Image,
            'search_query' => 'cat',
            'description' => VisualPlanService::PLACEHOLDER_VISUAL_PROMPT,
            'target_duration_seconds' => null,
            'aspect_ratio' => null,
            'status' => AssetRequirementStatus::Pending,
            'notes' => null,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk()
            ->assertJsonPath('data.ready', false)
            ->assertJsonFragment(['code' => 'ARCHIVED_VISUAL_PLAN'])
            ->assertJsonFragment(['code' => 'PLACEHOLDER_VISUAL_DESCRIPTION']);
    }
}
