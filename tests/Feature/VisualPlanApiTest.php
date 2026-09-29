<?php

namespace Tests\Feature;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Enums\ScriptStatus;
use App\Enums\VisualPlanItemType;
use App\Enums\VisualPlanSection;
use App\Enums\VisualPlanStatus;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Script;
use App\Models\Source;
use App\Models\User;
use App\Services\VisualPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VisualPlanApiTest extends TestCase
{
    use RefreshDatabase;

    private function readyProject(User $user): ContentProject
    {
        $project = ContentProject::factory()->for($user, 'creator')->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claims = [
            'Cats purr to communicate with humans',
            'Purring helps cats soothe themselves when stressed',
        ];
        foreach ($claims as $claimText) {
            $claim = ResearchClaim::factory()->create([
                'research_report_id' => $report->id,
                'claim' => $claimText,
                'status' => ResearchClaimStatus::Supported,
                'importance' => ResearchClaimImportance::High,
            ]);
            $claim->sources()->attach($source->id);
        }

        return $project;
    }

    private function scriptFor(ContentProject $project, int $version = 1, array $state = []): Script
    {
        $script = $project->script()->first() ?? Script::factory()->create(['content_project_id' => $project->id]);

        $versionModel = $script->versions()->create(array_merge([
            'version' => $version,
            'title' => 'How Cats Purr',
            'hook' => 'Why do cats purr?',
            'body' => "Cats purr to communicate with humans, and purring helps cats soothe themselves when stressed.\n",
            'closing' => 'And now you know.',
            'duration_seconds' => 45,
            'notes' => 'Record with soft background music.',
        ], $state));

        $script->update(['current_version_id' => $versionModel->id]);
        $script->load('currentVersion');

        return $script;
    }

    private function reviewScript(ContentProject $project, int $version = 1): Script
    {
        $script = $this->scriptFor($project, $version);
        $script->update(['status' => ScriptStatus::Review]);

        return $script->fresh();
    }

    private function service(): VisualPlanService
    {
        return app(VisualPlanService::class);
    }

    private function itemPayload(array $overrides = []): array
    {
        return array_merge([
            'section' => VisualPlanSection::Body->value,
            'narration_text' => 'A narration line.',
            'visual_type' => VisualPlanItemType::StockVideo->value,
            'visual_prompt' => 'Wide shot of a cat.',
            'duration_seconds' => 5,
        ], $overrides);
    }

    #[Test]
    public function test_endpoints_require_authentication(): void
    {
        Http::preventStrayRequests();

        $base = '/api/v1/projects/1/script/versions/1/visual-plan';

        $this->getJson($base)->assertUnauthorized();
        $this->postJson($base, [])->assertUnauthorized();
        $this->patchJson("{$base}/status", ['status' => VisualPlanStatus::Review->value])->assertUnauthorized();
        $this->getJson("{$base}/items")->assertUnauthorized();
        $this->postJson("{$base}/items", $this->itemPayload())->assertUnauthorized();
        $this->patchJson("{$base}/items/reorder", ['items' => []])->assertUnauthorized();
        $this->patchJson("{$base}/items/1", ['notes' => 'x'])->assertUnauthorized();
        $this->deleteJson("{$base}/items/1")->assertUnauthorized();
    }

    #[Test]
    public function test_show_returns_200_with_null_when_no_plan_exists(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan")
            ->assertOk()
            ->assertJsonPath('message', 'No visual plan yet for this script version.')
            ->assertJsonPath('data', null);
    }

    #[Test]
    public function test_show_returns_404_when_the_version_does_not_belong_to_the_script(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/script/versions/2/visual-plan")
            ->assertNotFound();
    }

    #[Test]
    public function test_show_returns_the_plan_bound_to_the_exact_version(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->reviewScript($project);
        $plan = $this->service()->createVisualPlan($project, 1, ['title' => 'Cat plan']);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan")
            ->assertOk()
            ->assertJsonPath('data.id', $plan->id)
            ->assertJsonPath('data.script_version_id', $script->currentVersion->id)
            ->assertJsonPath('data.status', VisualPlanStatus::Draft->value)
            ->assertJsonPath('data.status_label', 'Draft')
            ->assertJsonPath('data.title', 'Cat plan')
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.allowed_transitions.0.status', VisualPlanStatus::Review->value);
    }

    #[Test]
    public function test_store_returns_201_with_an_empty_draft_plan(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->reviewScript($project);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan", [])
            ->assertCreated()
            ->assertJsonPath('message', 'Visual plan created successfully.')
            ->assertJsonPath('data.status', VisualPlanStatus::Draft->value)
            ->assertJsonPath('data.script_version_id', $script->currentVersion->id)
            ->assertJsonPath('data.items', []);

        $this->assertDatabaseCount('visual_plans', 1);
        $this->assertDatabaseCount('visual_plan_items', 0);
    }

    #[Test]
    public function test_store_returns_409_for_a_duplicate_plan(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $this->service()->createVisualPlan($project, 1, []);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan", [])
            ->assertStatus(409);

        $this->assertDatabaseCount('visual_plans', 1);
    }

    #[Test]
    public function test_store_returns_422_when_the_script_is_not_ready(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->scriptFor($project);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan", [])
            ->assertUnprocessable();

        $this->assertDatabaseCount('visual_plans', 0);
    }

    #[Test]
    public function test_store_returns_404_when_the_version_is_missing(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/9/visual-plan", [])
            ->assertNotFound();
    }

    #[Test]
    public function test_store_from_script_seeds_hook_body_and_closing_items_in_order(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan", [
                'create_from_script' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.section', VisualPlanSection::Hook->value)
            ->assertJsonPath('data.items.0.order', 1)
            ->assertJsonPath('data.items.0.narration_text', 'Why do cats purr?')
            ->assertJsonPath('data.items.1.section', VisualPlanSection::Body->value)
            ->assertJsonPath('data.items.1.order', 2)
            ->assertJsonPath('data.items.2.section', VisualPlanSection::Closing->value)
            ->assertJsonPath('data.items.2.order', 3)
            ->assertJsonPath('data.items.0.visual_type', VisualPlanItemType::Other->value)
            ->assertJsonPath('data.items.0.visual_prompt', VisualPlanService::PLACEHOLDER_VISUAL_PROMPT)
            ->assertJsonPath('data.items.0.duration_seconds', VisualPlanService::DEFAULT_ITEM_DURATION_SECONDS);

        $this->assertDatabaseCount('visual_plan_items', 3);
    }

    #[Test]
    public function test_store_does_not_mutate_script_research_or_quality_state(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->reviewScript($project);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan", [
                'create_from_script' => true,
            ])
            ->assertCreated();

        $this->assertSame(ScriptStatus::Review, $script->fresh()->status);
        $this->assertSame(1, $script->fresh()->versions()->count());
        $this->assertDatabaseCount('script_version_research_claim', 0);
        $this->assertDatabaseCount('research_claims', 2);
    }

    #[Test]
    public function test_item_index_returns_items_ordered_by_order(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $plan = $this->service()->createVisualPlan($project, 1, []);
        $first = $this->service()->createItem($project, 1, $this->itemPayload(['narration_text' => 'First.']));
        $second = $this->service()->createItem($project, 1, $this->itemPayload(['narration_text' => 'Second.']));

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan/items")
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $first->id)
            ->assertJsonPath('data.items.1.id', $second->id)
            ->assertJsonPath('data.items.0.order', 1)
            ->assertJsonPath('data.items.0.section_label', 'Body');
    }

    #[Test]
    public function test_item_index_returns_404_when_no_plan_exists(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan/items")
            ->assertNotFound();
    }

    #[Test]
    public function test_item_store_returns_201_and_appends_to_the_plan(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $this->service()->createVisualPlan($project, 1, []);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan/items", $this->itemPayload())
            ->assertCreated()
            ->assertJsonPath('message', 'Visual plan item created successfully.')
            ->assertJsonPath('data.order', 1)
            ->assertJsonPath('data.section', VisualPlanSection::Body->value)
            ->assertJsonPath('data.visual_type', VisualPlanItemType::StockVideo->value)
            ->assertJsonPath('data.duration_seconds', 5);

        $this->assertDatabaseCount('visual_plan_items', 1);
    }

    #[Test]
    public function test_item_store_returns_422_for_invalid_enum_values_and_duration(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $this->service()->createVisualPlan($project, 1, []);

        $url = "/api/v1/projects/{$project->id}/script/versions/1/visual-plan/items";

        $this->actingAs($user, 'sanctum')
            ->postJson($url, $this->itemPayload(['section' => 'nope']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['section']);

        $this->actingAs($user, 'sanctum')
            ->postJson($url, $this->itemPayload(['visual_type' => 'nope']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['visual_type']);

        $this->actingAs($user, 'sanctum')
            ->postJson($url, $this->itemPayload(['duration_seconds' => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['duration_seconds']);

        $this->actingAs($user, 'sanctum')
            ->postJson($url, $this->itemPayload(['duration_seconds' => 61]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['duration_seconds']);
    }

    #[Test]
    public function test_item_store_returns_422_for_a_duplicate_order(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $this->service()->createVisualPlan($project, 1, []);
        $this->service()->createItem($project, 1, $this->itemPayload(['order' => 1]));

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan/items", $this->itemPayload(['order' => 1]))
            ->assertUnprocessable();

        $this->assertDatabaseCount('visual_plan_items', 1);
    }

    #[Test]
    public function test_item_update_applies_partial_changes(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $this->service()->createVisualPlan($project, 1, []);
        $item = $this->service()->createItem($project, 1, $this->itemPayload());

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan/items/{$item->id}", [
                'narration_text' => 'Rewritten narration.',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Visual plan item updated successfully.')
            ->assertJsonPath('data.narration_text', 'Rewritten narration.')
            ->assertJsonPath('data.section', VisualPlanSection::Body->value);
    }

    #[Test]
    public function test_item_update_returns_422_when_no_field_is_provided(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $this->service()->createVisualPlan($project, 1, []);
        $item = $this->service()->createItem($project, 1, $this->itemPayload());

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan/items/{$item->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['section']);
    }

    #[Test]
    public function test_item_update_returns_404_for_an_item_from_another_plan(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->prepareTwoPlans($user, $project);
        $foreignItem = $this->service()->createItem($project, 2, $this->itemPayload());

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan/items/{$foreignItem->id}", [
                'narration_text' => 'Should not apply.',
            ])
            ->assertNotFound();
    }

    #[Test]
    public function test_item_destroy_returns_200_and_removes_the_item(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $this->service()->createVisualPlan($project, 1, []);
        $item = $this->service()->createItem($project, 1, $this->itemPayload());

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Visual plan item deleted successfully.');

        $this->assertDatabaseCount('visual_plan_items', 0);
    }

    #[Test]
    public function test_item_destroy_returns_404_for_a_missing_item(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $this->service()->createVisualPlan($project, 1, []);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan/items/999")
            ->assertNotFound();
    }

    #[Test]
    public function test_reorder_returns_200_and_applies_the_new_order(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $this->service()->createVisualPlanFromScript($project, 1);
        $items = $this->service()->listItems($project, 1);
        [$first, $second, $third] = [$items[0], $items[1], $items[2]];

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan/items/reorder", [
                'items' => [
                    ['id' => $first->id, 'order' => 3],
                    ['id' => $second->id, 'order' => 1],
                    ['id' => $third->id, 'order' => 2],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Visual plan items reordered successfully.');

        $this->assertSame(
            [$second->id, $third->id, $first->id],
            $this->service()->listItems($project, 1)->pluck('id')->all()
        );
    }

    #[Test]
    public function test_reorder_returns_422_for_duplicate_orders(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $this->service()->createVisualPlanFromScript($project, 1);
        $items = $this->service()->listItems($project, 1);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan/items/reorder", [
                'items' => [
                    ['id' => $items[0]->id, 'order' => 1],
                    ['id' => $items[1]->id, 'order' => 1],
                    ['id' => $items[2]->id, 'order' => 3],
                ],
            ])
            ->assertUnprocessable();
    }

    #[Test]
    public function test_reorder_returns_422_for_an_item_from_another_plan(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->prepareTwoPlans($user, $project);
        $planOne = $this->service()->listItems($project, 1);
        $this->service()->createItem($project, 2, $this->itemPayload());
        $foreignItem = $this->service()->listItems($project, 2)->first();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan/items/reorder", [
                'items' => [
                    ['id' => $planOne[0]->id, 'order' => 1],
                    ['id' => $foreignItem->id, 'order' => 2],
                ],
            ])
            ->assertUnprocessable();
    }

    #[Test]
    public function test_status_transition_moves_the_plan_through_the_workflow(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $plan = $this->service()->createVisualPlan($project, 1, []);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan/status", [
                'status' => VisualPlanStatus::Review->value,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Visual plan status updated successfully.')
            ->assertJsonPath('data.status', VisualPlanStatus::Review->value)
            ->assertJsonPath('data.status_label', 'Review');

        $this->assertSame(VisualPlanStatus::Review, $plan->fresh()->status);
    }

    #[Test]
    public function test_status_transition_returns_422_for_an_invalid_transition(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $this->service()->createVisualPlan($project, 1, []);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan/status", [
                'status' => VisualPlanStatus::Approved->value,
            ])
            ->assertUnprocessable();
    }

    #[Test]
    public function test_status_transition_returns_422_for_the_same_status(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $this->service()->createVisualPlan($project, 1, []);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan/status", [
                'status' => VisualPlanStatus::Draft->value,
            ])
            ->assertUnprocessable();
    }

    #[Test]
    public function test_status_transition_returns_404_when_no_plan_exists(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan/status", [
                'status' => VisualPlanStatus::Review->value,
            ])
            ->assertNotFound();
    }

    /**
     * Create a reviewed script with two versions, each with its own plan.
     */
    private function prepareTwoPlans(User $user, ContentProject $project): void
    {
        $this->reviewScript($project);
        $this->scriptFor($project, 2);

        $this->service()->createVisualPlan($project, 1, []);
        $this->service()->createVisualPlan($project, 2, []);
        $this->service()->createItem($project, 1, $this->itemPayload());
        $this->service()->createItem($project, 1, $this->itemPayload(['order' => 2]));
    }
}
