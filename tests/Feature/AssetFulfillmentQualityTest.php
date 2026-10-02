<?php

namespace Tests\Feature;

use App\Enums\AssetRequirementAspectRatio;
use App\Enums\AssetRequirementStatus;
use App\Enums\AssetRequirementType;
use App\Enums\AssetType;
use App\Enums\ResearchStatus;
use App\Enums\VisualPlanItemType;
use App\Enums\VisualPlanStatus;
use App\Models\Asset;
use App\Models\AssetRequirement;
use App\Models\ContentProject;
use App\Models\ResearchReport;
use App\Models\Script;
use App\Models\Source;
use App\Models\User;
use App\Models\VisualPlan;
use App\Models\VisualPlanItem;
use App\Services\Assets\AssetFulfillmentQualityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssetFulfillmentQualityTest extends TestCase
{
    use RefreshDatabase;

    private AssetFulfillmentQualityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AssetFulfillmentQualityService::class);
    }

    #[Test]
    public function a_plan_with_no_requirements_is_not_asset_ready(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);

        $result = $this->service->evaluateVisualPlan($plan);

        $this->assertFalse($result['ready']);
        $this->assertSame(0, $result['summary']['total_requirements']);
        $this->assertSame('NO_ASSET_REQUIREMENTS', $result['blockers'][0]['code']);
    }

    #[Test]
    public function a_plan_with_fulfilled_valid_asset_is_asset_ready(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        $asset = Asset::factory()
            ->withVerifiedFile()
            ->create([
                'content_project_id' => $project->id,
                'type' => AssetType::Video,
                'width' => 1080,
                'height' => 1920,
                'aspect_ratio' => 0.5625,
                'duration_seconds' => 10,
            ]);

        $requirement = AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'requirement_type' => AssetRequirementType::Video,
            'aspect_ratio' => AssetRequirementAspectRatio::Portrait916,
            'target_duration_seconds' => 5,
            'status' => AssetRequirementStatus::Fulfilled,
            'selected_asset_id' => $asset->id,
        ]);

        $result = $this->service->evaluateVisualPlan($plan);

        $this->assertTrue($result['ready']);
        $this->assertSame(1, $result['summary']['total_requirements']);
        $this->assertSame(1, $result['summary']['fulfilled_requirements']);
        $this->assertSame(0, $result['summary']['unfulfilled_requirements']);
        $this->assertSame(0, $result['summary']['invalid_fulfillments']);
        $this->assertEmpty($result['blockers']);
    }

    #[Test]
    public function a_plan_with_unfulfilled_searching_requirement_is_not_ready(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => AssetRequirementStatus::Searching,
            'selected_asset_id' => null,
        ]);

        $result = $this->service->evaluateVisualPlan($plan);

        $this->assertFalse($result['ready']);
        $this->assertSame(1, $result['summary']['unfulfilled_requirements']);
        $this->assertSame('REQUIREMENT_NOT_FULFILLED', $result['blockers'][0]['code']);
    }

    #[Test]
    public function a_plan_with_selected_asset_but_searching_status_is_not_ready(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        $asset = Asset::factory()->create(['content_project_id' => $project->id]);

        AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => AssetRequirementStatus::Searching,
            'selected_asset_id' => $asset->id,
        ]);

        $result = $this->service->evaluateVisualPlan($plan);

        $this->assertFalse($result['ready']);
        $this->assertSame(1, $result['summary']['unfulfilled_requirements']);
        $this->assertSame('REQUIREMENT_SELECTED_BUT_NOT_FULFILLED', $result['blockers'][0]['code']);
    }

    #[Test]
    public function a_fulfilled_requirement_without_selected_asset_is_invalid(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => AssetRequirementStatus::Fulfilled,
            'selected_asset_id' => null,
        ]);

        $result = $this->service->evaluateVisualPlan($plan);

        $this->assertFalse($result['ready']);
        $this->assertSame(1, $result['summary']['invalid_fulfillments']);
        $this->assertSame('FULFILLED_REQUIREMENT_WITHOUT_SELECTION', $result['blockers'][0]['code']);
    }

    #[Test]
    public function a_fulfilled_requirement_with_cross_project_asset_is_invalid(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $otherProject = ContentProject::factory()->for($user, 'creator')->create();
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        $foreignAsset = Asset::factory()
            ->withVerifiedFile()
            ->create([
                'content_project_id' => $otherProject->id,
            ]);

        AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => AssetRequirementStatus::Fulfilled,
            'selected_asset_id' => $foreignAsset->id,
        ]);

        $result = $this->service->evaluateVisualPlan($plan);

        $this->assertFalse($result['ready']);
        $this->assertSame(1, $result['summary']['invalid_fulfillments']);
        $this->assertSame('SELECTED_ASSET_CROSS_PROJECT', $result['blockers'][0]['code']);
    }

    #[Test]
    public function a_fulfilled_requirement_with_fileless_asset_is_invalid(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        $filelessAsset = Asset::factory()->create([
            'content_project_id' => $project->id,
            'file_path' => null,
        ]);

        AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => AssetRequirementStatus::Fulfilled,
            'selected_asset_id' => $filelessAsset->id,
        ]);

        $result = $this->service->evaluateVisualPlan($plan);

        $this->assertFalse($result['ready']);
        $this->assertSame(1, $result['summary']['invalid_fulfillments']);
        $this->assertSame('SELECTED_ASSET_HAS_NO_FILE', $result['blockers'][0]['code']);
    }

    #[Test]
    public function a_fulfilled_requirement_with_incompatible_asset_is_invalid(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        $incompatibleAsset = Asset::factory()
            ->withVerifiedFile()
            ->create([
                'content_project_id' => $project->id,
                'type' => AssetType::Audio, // Type mismatch for Video requirement
            ]);

        AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'requirement_type' => AssetRequirementType::Video,
            'status' => AssetRequirementStatus::Fulfilled,
            'selected_asset_id' => $incompatibleAsset->id,
        ]);

        $result = $this->service->evaluateVisualPlan($plan);

        $this->assertFalse($result['ready']);
        $this->assertSame(1, $result['summary']['invalid_fulfillments']);
        $this->assertSame('SELECTED_ASSET_INCOMPATIBLE', $result['blockers'][0]['code']);
    }

    #[Test]
    public function api_endpoint_returns_asset_readiness_evaluation(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => AssetRequirementStatus::Searching,
        ]);

        $versionNumber = $plan->scriptVersion->version;

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/script/versions/{$versionNumber}/visual-plan/asset-readiness");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.visual_plan_id', $plan->id)
            ->assertJsonPath('data.ready', false)
            ->assertJsonPath('data.summary.total_requirements', 1);
    }

    #[Test]
    public function api_endpoint_requires_authentication(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $versionNumber = $plan->scriptVersion->version;

        $response = $this->getJson("/api/v1/projects/{$project->id}/script/versions/{$versionNumber}/visual-plan/asset-readiness");

        $response->assertUnauthorized();
    }

    #[Test]
    public function api_endpoint_returns_404_for_cross_project_or_missing_plan(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $otherProject = ContentProject::factory()->for($user, 'creator')->create();
        $plan = $this->planFor($project);
        $versionNumber = $plan->scriptVersion->version;

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$otherProject->id}/script/versions/{$versionNumber}/visual-plan/asset-readiness");

        $response->assertNotFound();
    }

    private function readyProject(User $user): ContentProject
    {
        $project = ContentProject::factory()->for($user, 'creator')->create();
        $report = ResearchReport::factory()->create([
            'content_project_id' => $project->id,
            'status' => ResearchStatus::Completed,
        ]);
        Source::factory()->create(['research_report_id' => $report->id]);

        return $project;
    }

    private function scriptFor(ContentProject $project, int $version = 1): Script
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

        $script->update(['current_version_id' => $versionModel->id]);
        $script->load('currentVersion');

        return $script;
    }

    private function planFor(ContentProject $project, int $version = 1): VisualPlan
    {
        $script = $this->scriptFor($project, $version);

        return VisualPlan::factory()->create([
            'content_project_id' => $project->id,
            'script_version_id' => $script->currentVersion->id,
            'status' => VisualPlanStatus::Draft,
        ]);
    }

    private function itemFor(VisualPlan $plan): VisualPlanItem
    {
        return $plan->items()->create([
            'order' => 1,
            'section' => 'body',
            'narration_text' => 'Cats purr to communicate with humans.',
            'visual_type' => VisualPlanItemType::AnimalClip,
            'visual_prompt' => 'Close up of a cat purring on a blanket.',
            'duration_seconds' => 5,
            'notes' => null,
        ]);
    }
}
