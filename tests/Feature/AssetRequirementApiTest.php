<?php

namespace Tests\Feature;

use App\Enums\AssetRequirementAspectRatio;
use App\Enums\AssetRequirementStatus;
use App\Enums\AssetRequirementType;
use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Enums\ScriptStatus;
use App\Enums\VisualPlanItemType;
use App\Enums\VisualPlanSection;
use App\Models\AssetRequirement;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Script;
use App\Models\Source;
use App\Models\User;
use App\Models\VisualPlan;
use App\Models\VisualPlanItem;
use App\Services\VisualPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssetRequirementApiTest extends TestCase
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

    private function reviewScript(ContentProject $project, int $version = 1): Script
    {
        $script = $project->script()->first() ?? Script::factory()->create(['content_project_id' => $project->id]);

        $versionModel = $script->versions()->create([
            'version' => $version,
            'title' => 'How Cats Purr',
            'hook' => 'Why do cats purr?',
            'body' => "Cats purr to communicate with humans, and purring helps cats soothe themselves when stressed.\n",
            'closing' => 'And now you know.',
            'duration_seconds' => 45,
            'notes' => null,
        ]);

        $script->update(['current_version_id' => $versionModel->id, 'status' => ScriptStatus::Review]);
        $script->load('currentVersion');

        return $script;
    }

    private function service(): VisualPlanService
    {
        return app(VisualPlanService::class);
    }

    private function planWithItems(ContentProject $project, int $version = 1, int $itemCount = 2): VisualPlan
    {
        $this->reviewScript($project, $version);
        $plan = $this->service()->createVisualPlan($project, $version, []);

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

    private function url(ContentProject $project, int $version, ?int $item = null, ?int $requirement = null): string
    {
        $url = "/api/v1/projects/{$project->id}/script/versions/{$version}/visual-plan";

        if ($item !== null) {
            $url .= "/items/{$item}/asset-requirements";
        }

        if ($requirement !== null) {
            $url .= "/{$requirement}";
        }

        return $url;
    }

    private function requirementPayload(array $overrides = []): array
    {
        return array_merge([
            'requirement_type' => AssetRequirementType::Video->value,
            'search_query' => 'calm cat footage',
            'description' => 'A calm cat sitting on a rug.',
            'target_duration_seconds' => 5,
            'aspect_ratio' => AssetRequirementAspectRatio::Portrait916->value,
        ], $overrides);
    }

    #[Test]
    public function test_asset_requirement_endpoints_require_authentication(): void
    {
        Http::preventStrayRequests();

        $base = '/api/v1/projects/1/script/versions/1/visual-plan';

        $this->getJson("{$base}/items/1/asset-requirements")->assertUnauthorized();
        $this->postJson("{$base}/items/1/asset-requirements", $this->requirementPayload())->assertUnauthorized();
        $this->patchJson("{$base}/items/1/asset-requirements/1", ['notes' => 'x'])->assertUnauthorized();
        $this->patchJson("{$base}/items/1/asset-requirements/1/status", ['status' => 'searching'])->assertUnauthorized();
        $this->deleteJson("{$base}/items/1/asset-requirements/1")->assertUnauthorized();
        $this->postJson("{$base}/asset-requirements/generate")->assertUnauthorized();
    }

    #[Test]
    public function test_index_returns_200_with_an_empty_list_for_a_fresh_item(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project, 1, 1);
        $item = $plan->items()->first();

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project, 1, $item->id))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.requirements', []);
    }

    #[Test]
    public function test_index_returns_404_when_the_item_does_not_exist(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->planWithItems($project);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project, 1, 9999))
            ->assertNotFound();
    }

    #[Test]
    public function test_index_returns_404_when_the_visual_plan_does_not_exist(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project, 1, 1))
            ->assertNotFound();
    }

    #[Test]
    public function test_index_returns_404_for_an_item_of_another_project(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $otherProject = $this->readyProject($user);
        $otherPlan = $this->planWithItems($otherProject);
        $otherItem = $otherPlan->items()->first();
        $this->planWithItems($project);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project, 1, $otherItem->id))
            ->assertNotFound();
    }

    #[Test]
    public function test_store_creates_a_pending_requirement(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();

        $this->actingAs($user, 'sanctum')
            ->postJson($this->url($project, 1, $item->id), $this->requirementPayload())
            ->assertCreated()
            ->assertJsonPath('data.requirement_type', AssetRequirementType::Video->value)
            ->assertJsonPath('data.requirement_type_label', 'Video')
            ->assertJsonPath('data.status', AssetRequirementStatus::Pending->value)
            ->assertJsonPath('data.aspect_ratio', '9:16')
            ->assertJsonPath('data.description', 'A calm cat sitting on a rug.');

        $this->assertDatabaseHas('asset_requirements', [
            'visual_plan_item_id' => $item->id,
            'status' => AssetRequirementStatus::Pending->value,
        ]);
    }

    #[Test]
    public function test_store_accepts_an_item_with_several_requirements(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();

        $this->actingAs($user, 'sanctum')
            ->postJson($this->url($project, 1, $item->id), $this->requirementPayload())
            ->assertCreated();
        $this->actingAs($user, 'sanctum')
            ->postJson($this->url($project, 1, $item->id), $this->requirementPayload([
                'requirement_type' => AssetRequirementType::Audio->value,
            ]))
            ->assertCreated();

        $this->assertDatabaseCount('asset_requirements', 2);
    }

    #[Test]
    public function test_store_ignores_a_client_supplied_status(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();

        $this->actingAs($user, 'sanctum')
            ->postJson($this->url($project, 1, $item->id), $this->requirementPayload([
                'status' => AssetRequirementStatus::Fulfilled->value,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.status', AssetRequirementStatus::Pending->value);
    }

    #[Test]
    public function test_store_ignores_a_client_supplied_id(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson($this->url($project, 1, $item->id), $this->requirementPayload(['id' => 999]))
            ->assertCreated();

        $this->assertNotSame(999, $response->json('data.id'));
        $this->assertDatabaseHas('asset_requirements', ['id' => $response->json('data.id')]);
    }

    #[Test]
    public function test_store_validates_the_requirement_type(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();

        $this->actingAs($user, 'sanctum')
            ->postJson($this->url($project, 1, $item->id), $this->requirementPayload([
                'requirement_type' => 'hologram',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('requirement_type');
    }

    #[Test]
    public function test_store_requires_a_description(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();

        $payload = $this->requirementPayload();
        unset($payload['description']);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->url($project, 1, $item->id), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('description');
    }

    #[Test]
    public function test_store_validates_the_aspect_ratio(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();

        $this->actingAs($user, 'sanctum')
            ->postJson($this->url($project, 1, $item->id), $this->requirementPayload([
                'aspect_ratio' => '21:9',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('aspect_ratio');
    }

    #[Test]
    public function test_update_changes_only_the_sent_fields(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'description' => 'Original description.',
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson($this->url($project, 1, $item->id, $requirement->id), ['search_query' => 'new query'])
            ->assertOk()
            ->assertJsonPath('data.search_query', 'new query')
            ->assertJsonPath('data.description', 'Original description.');
    }

    #[Test]
    public function test_update_cannot_change_the_status(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => AssetRequirementStatus::Pending,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson($this->url($project, 1, $item->id, $requirement->id), [
                'notes' => 'A note.',
                'status' => AssetRequirementStatus::Fulfilled->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', AssetRequirementStatus::Pending->value);
    }

    #[Test]
    public function test_update_requires_at_least_one_field(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = AssetRequirement::factory()->create(['visual_plan_item_id' => $item->id]);

        $this->actingAs($user, 'sanctum')
            ->patchJson($this->url($project, 1, $item->id, $requirement->id), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('requirement_type');
    }

    #[Test]
    public function test_update_returns_404_for_a_requirement_of_another_item(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project);
        $firstItem = $plan->items()->where('order', 1)->first();
        $secondItem = $plan->items()->where('order', 2)->first();
        $requirement = AssetRequirement::factory()->create([
            'visual_plan_item_id' => $firstItem->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson($this->url($project, 1, $secondItem->id, $requirement->id), ['notes' => 'x'])
            ->assertNotFound();
    }

    #[Test]
    public function test_destroy_removes_the_requirement(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = AssetRequirement::factory()->create(['visual_plan_item_id' => $item->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson($this->url($project, 1, $item->id, $requirement->id))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('asset_requirements', ['id' => $requirement->id]);
    }

    #[Test]
    public function test_destroy_returns_404_when_the_requirement_does_not_exist(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();

        $this->actingAs($user, 'sanctum')
            ->deleteJson($this->url($project, 1, $item->id, 9999))
            ->assertNotFound();
    }

    #[Test]
    public function test_destroying_a_plan_item_cascades_to_its_requirements(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = AssetRequirement::factory()->create(['visual_plan_item_id' => $item->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan/items/{$item->id}")
            ->assertOk();

        $this->assertDatabaseMissing('asset_requirements', ['id' => $requirement->id]);
    }

    #[Test]
    public function test_transition_status_moves_a_requirement_to_searching(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => AssetRequirementStatus::Pending,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson($this->url($project, 1, $item->id, $requirement->id).'/status', ['status' => 'searching'])
            ->assertOk()
            ->assertJsonPath('data.status', AssetRequirementStatus::Searching->value);
    }

    #[Test]
    public function test_transition_status_returns_422_for_an_illegal_move(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => AssetRequirementStatus::Pending,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson($this->url($project, 1, $item->id, $requirement->id).'/status', ['status' => 'fulfilled'])
            ->assertUnprocessable();

        $this->assertDatabaseHas('asset_requirements', [
            'id' => $requirement->id,
            'status' => AssetRequirementStatus::Pending->value,
        ]);
    }

    #[Test]
    public function test_transition_status_rejects_an_unknown_status(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = AssetRequirement::factory()->create(['visual_plan_item_id' => $item->id]);

        $this->actingAs($user, 'sanctum')
            ->patchJson($this->url($project, 1, $item->id, $requirement->id).'/status', ['status' => 'downloaded'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    #[Test]
    public function test_transition_status_returns_404_for_an_unknown_requirement(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();

        $this->actingAs($user, 'sanctum')
            ->patchJson($this->url($project, 1, $item->id, 9999).'/status', ['status' => 'searching'])
            ->assertNotFound();
    }

    #[Test]
    public function test_generate_creates_one_requirement_per_item(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project, 1, 3);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan/asset-requirements/generate")
            ->assertOk()
            ->assertJsonPath('data.created', 3)
            ->assertJsonPath('data.existing', 0)
            ->assertJsonPath('data.skipped', 0);

        $this->assertSame(3, $plan->items()->withCount('assetRequirements')->get()->sum('asset_requirements_count'));
    }

    #[Test]
    public function test_generate_is_idempotent_on_a_second_call(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project, 1, 2);
        $url = "/api/v1/projects/{$project->id}/script/versions/1/visual-plan/asset-requirements/generate";

        $this->actingAs($user, 'sanctum')->postJson($url)->assertOk();
        $this->actingAs($user, 'sanctum')
            ->postJson($url)
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.existing', 2);

        $this->assertSame(2, $plan->items()->withCount('assetRequirements')->get()->sum('asset_requirements_count'));
    }

    #[Test]
    public function test_generate_returns_404_without_a_visual_plan(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan/asset-requirements/generate")
            ->assertNotFound();
    }

    #[Test]
    public function test_item_resource_includes_its_requirements(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project, 1, 1);
        $item = $plan->items()->first();
        $requirement = AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'requirement_type' => AssetRequirementType::Image,
            'search_query' => 'tabby cat portrait',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan/items")
            ->assertOk()
            ->assertJsonPath('data.items.0.asset_requirements.0.id', $requirement->id)
            ->assertJsonPath('data.items.0.asset_requirements.0.requirement_type', 'image')
            ->assertJsonPath('data.items.0.asset_requirements.0.search_query', 'tabby cat portrait');
    }

    #[Test]
    public function test_requirement_resource_exposes_the_allowed_transitions(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => AssetRequirementStatus::Pending,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project, 1, $item->id))
            ->assertOk()
            ->assertJsonCount(2, 'data.requirements.0.allowed_transitions')
            ->assertJsonPath('data.requirements.0.allowed_transitions.0.status', 'searching')
            ->assertJsonPath('data.requirements.0.allowed_transitions.1.status', 'skipped')
            ->assertJsonPath('data.requirements.0.allowed_transitions.0.action', 'Start Searching');
    }

    #[Test]
    public function test_a_fulfilled_requirement_exposes_no_transitions(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => AssetRequirementStatus::Fulfilled,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project, 1, $item->id))
            ->assertOk()
            ->assertJsonCount(0, 'data.requirements.0.allowed_transitions');
    }

    #[Test]
    public function test_generated_requirements_use_the_documented_defaults(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project, 1, 1);
        $item = $plan->items()->first();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/1/visual-plan/asset-requirements/generate")
            ->assertOk();

        $requirement = $item->assetRequirements()->first();
        $this->assertSame(AssetRequirementStatus::Pending, $requirement->status);
        $this->assertSame(AssetRequirementAspectRatio::Portrait916, $requirement->aspect_ratio);
        $this->assertSame('Visual prompt 1.', $requirement->description);
        $this->assertSame('Visual prompt 1.', $requirement->search_query);
    }

    #[Test]
    public function test_requirements_stay_scoped_to_their_own_script_version(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project, 1, 1);
        $item = $plan->items()->first();
        $requirement = AssetRequirement::factory()->create(['visual_plan_item_id' => $item->id]);
        $this->reviewScript($project, 2);

        $this->actingAs($user, 'sanctum')
            ->patchJson($this->url($project, 2, $item->id, $requirement->id), ['notes' => 'x'])
            ->assertNotFound();
    }

    #[Test]
    public function test_visual_plan_item_cascade_still_holds_after_adding_requirements(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project);
        $item = $plan->items()->first();
        VisualPlanItem::query()->whereKey($item->id)->delete();

        $this->assertDatabaseMissing('visual_plan_items', ['id' => $item->id]);
    }
}
