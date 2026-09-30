<?php

namespace Tests\Feature;

use App\Enums\AssetRequirementAspectRatio;
use App\Enums\AssetRequirementStatus;
use App\Enums\AssetRequirementType;
use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Enums\ScriptStatus;
use App\Enums\VisualPlanItemType;
use App\Enums\VisualPlanSection;
use App\Models\Asset;
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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    private function assetsUrl(
        ContentProject $project,
        int $version,
        ?int $item = null,
        ?int $requirement = null,
        ?int $asset = null
    ): string {
        $url = $this->url($project, $version, $item, $requirement);

        if ($requirement !== null) {
            $url .= '/assets';
        }

        if ($asset !== null) {
            $url .= "/{$asset}";
        }

        return $url;
    }

    private function requirementFor(VisualPlanItem $item): AssetRequirement
    {
        return AssetRequirement::factory()->create(['visual_plan_item_id' => $item->id]);
    }

    private function assetFor(ContentProject $project, array $overrides = []): Asset
    {
        return Asset::factory()->create(
            array_merge(['content_project_id' => $project->id], $overrides)
        );
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
        $this->getJson("{$base}/items/1/asset-requirements/1/assets")->assertUnauthorized();
        $this->postJson("{$base}/items/1/asset-requirements/1/assets", ['asset_id' => 1])->assertUnauthorized();
        $this->deleteJson("{$base}/items/1/asset-requirements/1/assets/1")->assertUnauthorized();
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

    // -----------------------------------------------------------------
    // Asset <-> AssetRequirement relationship
    // -----------------------------------------------------------------

    #[Test]
    public function test_an_asset_requirement_can_hold_several_assets(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);

        $first = $this->assetFor($project, ['title' => 'Cat walking 01']);
        $second = $this->assetFor($project, ['title' => 'Cat walking 02']);
        $third = $this->assetFor($project, ['title' => 'Cat walking 03']);

        $requirement->assets()->attach([$first->id, $second->id, $third->id]);

        $this->assertCount(3, $requirement->assets()->get());
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id, $third->id],
            $requirement->assets()->pluck('assets.id')->all()
        );
    }

    #[Test]
    public function test_an_asset_can_belong_to_several_asset_requirements(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();

        $first = $this->requirementFor($item);
        $second = $this->requirementFor($item);
        $asset = $this->assetFor($project);

        $asset->assetRequirements()->attach([$first->id, $second->id]);

        $this->assertCount(2, $asset->assetRequirements()->get());
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $asset->assetRequirements()->pluck('asset_requirements.id')->all()
        );
    }

    #[Test]
    public function test_the_pivot_records_timestamps(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);
        $asset = $this->assetFor($project);

        $requirement->assets()->attach($asset->id);

        $pivot = DB::table('asset_requirement_asset')
            ->where('asset_requirement_id', $requirement->id)
            ->where('asset_id', $asset->id)
            ->first();

        $this->assertNotNull($pivot);
        $this->assertNotNull($pivot->created_at);
        $this->assertNotNull($pivot->updated_at);
    }

    #[Test]
    public function test_the_database_refuses_a_duplicate_pair(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);
        $asset = $this->assetFor($project);

        $requirement->assets()->attach($asset->id);

        // The unique constraint is the safety net behind the service check: it
        // has to hold even when the service is bypassed entirely.
        $this->expectException(QueryException::class);

        DB::table('asset_requirement_asset')->insert([
            'asset_requirement_id' => $requirement->id,
            'asset_id' => $asset->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // -----------------------------------------------------------------
    // Attach
    // -----------------------------------------------------------------

    #[Test]
    public function test_attach_returns_201_with_the_asset_in_the_response(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);
        $asset = $this->assetFor($project, ['title' => 'Cat walking 01']);

        $this->actingAs($user, 'sanctum')
            ->postJson(
                $this->assetsUrl($project, 1, $item->id, $requirement->id),
                ['asset_id' => $asset->id]
            )
            ->assertCreated()
            ->assertJsonPath('data.id', $asset->id)
            ->assertJsonPath('data.title', 'Cat walking 01')
            ->assertJsonPath('data.content_project_id', $project->id)
            ->assertJsonPath('data.type', $asset->type->value)
            ->assertJsonPath('data.status', $asset->status->value);
    }

    #[Test]
    public function test_attach_updates_the_requirement_relationship(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);
        $asset = $this->assetFor($project);

        $this->assertCount(0, $requirement->assets()->get());

        $this->actingAs($user, 'sanctum')
            ->postJson(
                $this->assetsUrl($project, 1, $item->id, $requirement->id),
                ['asset_id' => $asset->id]
            )
            ->assertCreated();

        $this->assertDatabaseHas('asset_requirement_asset', [
            'asset_requirement_id' => $requirement->id,
            'asset_id' => $asset->id,
        ]);
        $this->assertTrue($requirement->fresh()->assets()->whereKey($asset->id)->exists());
    }

    #[Test]
    public function test_attach_updates_the_asset_relationship(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);
        $asset = $this->assetFor($project);

        $this->actingAs($user, 'sanctum')
            ->postJson(
                $this->assetsUrl($project, 1, $item->id, $requirement->id),
                ['asset_id' => $asset->id]
            )
            ->assertCreated();

        $this->assertTrue($asset->fresh()->assetRequirements()->whereKey($requirement->id)->exists());
    }

    #[Test]
    public function test_attach_rejects_a_missing_or_non_integer_asset_id(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);
        $url = $this->assetsUrl($project, 1, $item->id, $requirement->id);

        $this->actingAs($user, 'sanctum')
            ->postJson($url, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('asset_id');

        $this->actingAs($user, 'sanctum')
            ->postJson($url, ['asset_id' => 'not-an-id'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('asset_id');
    }

    #[Test]
    public function test_attach_returns_404_for_an_asset_that_does_not_exist(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);

        $this->actingAs($user, 'sanctum')
            ->postJson(
                $this->assetsUrl($project, 1, $item->id, $requirement->id),
                ['asset_id' => 999999]
            )
            ->assertNotFound();
    }

    // -----------------------------------------------------------------
    // Attach semantics: association is not fulfillment
    // -----------------------------------------------------------------

    #[Test]
    public function test_attach_changes_neither_the_requirement_nor_the_asset_status(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);
        $asset = $this->assetFor($project, ['status' => AssetStatus::Available]);

        $this->actingAs($user, 'sanctum')
            ->postJson(
                $this->assetsUrl($project, 1, $item->id, $requirement->id),
                ['asset_id' => $asset->id]
            )
            ->assertCreated();

        // A candidate is not a fulfillment, a selection, or an approval, so
        // neither side may be advanced by being associated.
        $this->assertSame(AssetRequirementStatus::Pending, $requirement->fresh()->status);
        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
    }

    #[Test]
    public function test_attach_allows_an_asset_whose_type_does_not_match_the_requirement(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);

        $this->assertSame(AssetRequirementType::Video, $requirement->requirement_type);

        $image = $this->assetFor($project, ['type' => AssetType::Image]);

        $this->actingAs($user, 'sanctum')
            ->postJson(
                $this->assetsUrl($project, 1, $item->id, $requirement->id),
                ['asset_id' => $image->id]
            )
            ->assertCreated()
            ->assertJsonPath('data.id', $image->id);

        $this->assertTrue($requirement->fresh()->assets()->whereKey($image->id)->exists());
    }

    // -----------------------------------------------------------------
    // Duplicate protection
    // -----------------------------------------------------------------

    #[Test]
    public function test_a_second_attach_of_the_same_asset_returns_409(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);
        $asset = $this->assetFor($project);
        $url = $this->assetsUrl($project, 1, $item->id, $requirement->id);

        $this->actingAs($user, 'sanctum')
            ->postJson($url, ['asset_id' => $asset->id])
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->postJson($url, ['asset_id' => $asset->id])
            ->assertStatus(409);
    }

    #[Test]
    public function test_a_rejected_duplicate_attach_does_not_add_a_second_pivot_row(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);
        $asset = $this->assetFor($project);
        $url = $this->assetsUrl($project, 1, $item->id, $requirement->id);

        $this->actingAs($user, 'sanctum')->postJson($url, ['asset_id' => $asset->id])->assertCreated();
        $this->actingAs($user, 'sanctum')->postJson($url, ['asset_id' => $asset->id])->assertStatus(409);

        $this->assertSame(1, DB::table('asset_requirement_asset')
            ->where('asset_requirement_id', $requirement->id)
            ->where('asset_id', $asset->id)
            ->count());
    }

    #[Test]
    public function test_the_same_asset_may_be_attached_to_two_different_requirements(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $first = $this->requirementFor($item);
        $second = $this->requirementFor($item);
        $asset = $this->assetFor($project);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->assetsUrl($project, 1, $item->id, $first->id), ['asset_id' => $asset->id])
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->postJson($this->assetsUrl($project, 1, $item->id, $second->id), ['asset_id' => $asset->id])
            ->assertCreated();

        $this->assertCount(2, $asset->fresh()->assetRequirements()->get());
    }

    // -----------------------------------------------------------------
    // List
    // -----------------------------------------------------------------

    #[Test]
    public function test_list_returns_200_with_an_empty_list_when_nothing_is_associated(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);
        $this->assetFor($project);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->assetsUrl($project, 1, $item->id, $requirement->id))
            ->assertOk()
            ->assertJsonCount(0, 'data.assets');
    }

    #[Test]
    public function test_list_returns_only_the_assets_associated_with_that_requirement(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);
        $otherRequirement = $this->requirementFor($item);

        $associated = $this->assetFor($project, ['title' => 'Associated']);
        $elsewhere = $this->assetFor($project, ['title' => 'Belongs to another requirement']);
        $unattached = $this->assetFor($project, ['title' => 'Never associated']);

        $requirement->assets()->attach($associated->id);
        $otherRequirement->assets()->attach($elsewhere->id);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->assetsUrl($project, 1, $item->id, $requirement->id))
            ->assertOk()
            ->assertJsonCount(1, 'data.assets')
            ->assertJsonPath('data.assets.0.id', $associated->id)
            ->assertJsonPath('data.assets.0.title', 'Associated');
    }

    #[Test]
    public function test_list_never_returns_an_asset_from_another_project(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $mine = $this->readyProject($user);
        $other = $this->readyProject($user);
        $item = $this->planWithItems($mine, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);

        $ownAsset = $this->assetFor($mine, ['title' => 'Mine']);
        $foreignAsset = $this->assetFor($other, ['title' => 'Theirs']);

        $requirement->assets()->attach($ownAsset->id);

        // Written straight into the pivot to stand in for a row that predates
        // the scope check, so the list is proven to be scoped on its own rather
        // than only benefiting from attach refusing the same pair.
        DB::table('asset_requirement_asset')->insert([
            'asset_requirement_id' => $requirement->id,
            'asset_id' => $foreignAsset->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->assetsUrl($mine, 1, $item->id, $requirement->id))
            ->assertOk()
            ->assertJsonCount(1, 'data.assets')
            ->assertJsonPath('data.assets.0.id', $ownAsset->id)
            ->assertJsonMissing(['id' => $foreignAsset->id]);
    }

    #[Test]
    public function test_list_returns_404_for_a_requirement_of_another_item(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project, 1, 2);
        $items = $plan->items()->orderBy('id')->get();
        $requirement = $this->requirementFor($items->last());

        $this->actingAs($user, 'sanctum')
            ->getJson($this->assetsUrl($project, 1, $items->first()->id, $requirement->id))
            ->assertNotFound();
    }

    // -----------------------------------------------------------------
    // Detach
    // -----------------------------------------------------------------

    #[Test]
    public function test_detach_removes_the_association_only(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);
        $asset = $this->assetFor($project);
        $requirement->assets()->attach($asset->id);

        $this->actingAs($user, 'sanctum')
            ->deleteJson($this->assetsUrl($project, 1, $item->id, $requirement->id, $asset->id))
            ->assertOk();

        $this->assertDatabaseMissing('asset_requirement_asset', [
            'asset_requirement_id' => $requirement->id,
            'asset_id' => $asset->id,
        ]);

        // Detaching is a metadata operation. Neither domain entity is removed.
        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
        $this->assertDatabaseHas('asset_requirements', ['id' => $requirement->id]);
    }

    #[Test]
    public function test_detach_returns_404_when_the_asset_is_not_associated(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);
        $asset = $this->assetFor($project);

        $this->actingAs($user, 'sanctum')
            ->deleteJson($this->assetsUrl($project, 1, $item->id, $requirement->id, $asset->id))
            ->assertNotFound();
    }

    #[Test]
    public function test_detach_returns_404_for_an_asset_from_another_project(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $mine = $this->readyProject($user);
        $other = $this->readyProject($user);
        $item = $this->planWithItems($mine, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);
        $foreignAsset = $this->assetFor($other);

        $this->actingAs($user, 'sanctum')
            ->deleteJson($this->assetsUrl($mine, 1, $item->id, $requirement->id, $foreignAsset->id))
            ->assertNotFound();
    }

    // -----------------------------------------------------------------
    // Cross-project security
    // -----------------------------------------------------------------

    #[Test]
    public function test_attach_rejects_an_asset_from_another_project_owned_by_the_same_user(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $mine = $this->readyProject($user);
        $alsoMine = $this->readyProject($user);
        $item = $this->planWithItems($mine, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);
        $foreignAsset = $this->assetFor($alsoMine);

        // The permission check passes here because one user owns both
        // projects, so only the scope check itself can stop this.
        $this->actingAs($user, 'sanctum')
            ->postJson(
                $this->assetsUrl($mine, 1, $item->id, $requirement->id),
                ['asset_id' => $foreignAsset->id]
            )
            ->assertNotFound();

        $this->assertDatabaseMissing('asset_requirement_asset', [
            'asset_requirement_id' => $requirement->id,
            'asset_id' => $foreignAsset->id,
        ]);
    }

    #[Test]
    public function test_attach_rejects_an_asset_from_another_users_project(): void
    {
        Http::preventStrayRequests();
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = $this->readyProject($owner);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);
        $foreignAsset = $this->assetFor($this->readyProject($stranger));

        $this->actingAs($owner, 'sanctum')
            ->postJson(
                $this->assetsUrl($project, 1, $item->id, $requirement->id),
                ['asset_id' => $foreignAsset->id]
            )
            ->assertNotFound();

        $this->assertDatabaseMissing('asset_requirement_asset', [
            'asset_requirement_id' => $requirement->id,
            'asset_id' => $foreignAsset->id,
        ]);
    }

    #[Test]
    public function test_a_stranger_cannot_read_or_write_another_projects_requirement_assets(): void
    {
        Http::preventStrayRequests();
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $project = $this->readyProject($owner);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);
        $asset = $this->assetFor($project);
        $url = $this->assetsUrl($project, 1, $item->id, $requirement->id);

        $this->actingAs($stranger, 'sanctum')->getJson($url)->assertForbidden();
        $this->actingAs($stranger, 'sanctum')->postJson($url, ['asset_id' => $asset->id])->assertForbidden();
        $this->actingAs($stranger, 'sanctum')
            ->deleteJson("{$url}/{$asset->id}")
            ->assertForbidden();

        $this->assertDatabaseMissing('asset_requirement_asset', [
            'asset_requirement_id' => $requirement->id,
            'asset_id' => $asset->id,
        ]);
    }

    #[Test]
    public function test_attach_rejects_a_requirement_from_another_script_version(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);
        $asset = $this->assetFor($project);
        $this->reviewScript($project, 2);

        $this->actingAs($user, 'sanctum')
            ->postJson(
                $this->assetsUrl($project, 2, $item->id, $requirement->id),
                ['asset_id' => $asset->id]
            )
            ->assertNotFound();
    }

    // -----------------------------------------------------------------
    // Database integrity
    // -----------------------------------------------------------------

    #[Test]
    public function test_deleting_an_asset_removes_the_pivot_and_keeps_the_requirement(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);
        $asset = $this->assetFor($project);
        $requirement->assets()->attach($asset->id);

        Asset::query()->whereKey($asset->id)->delete();

        $this->assertDatabaseMissing('asset_requirement_asset', [
            'asset_requirement_id' => $requirement->id,
            'asset_id' => $asset->id,
        ]);
        $this->assertDatabaseHas('asset_requirements', ['id' => $requirement->id]);
    }

    #[Test]
    public function test_deleting_a_requirement_removes_the_pivot_and_keeps_the_asset(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project, 1, 1)->items()->first();
        $requirement = $this->requirementFor($item);
        $asset = $this->assetFor($project);
        $requirement->assets()->attach($asset->id);

        AssetRequirement::query()->whereKey($requirement->id)->delete();

        $this->assertDatabaseMissing('asset_requirement_asset', [
            'asset_requirement_id' => $requirement->id,
            'asset_id' => $asset->id,
        ]);
        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }
}
