<?php

namespace Tests\Feature;

use App\Enums\AssetRequirementAspectRatio;
use App\Enums\AssetRequirementStatus;
use App\Enums\AssetRequirementType;
use App\Enums\AssetType;
use App\Enums\VisualPlanItemType;
use App\Enums\VisualPlanStatus;
use App\Models\Asset;
use App\Models\AssetRequirement;
use App\Models\ContentProject;
use App\Models\Script;
use App\Models\User;
use App\Models\VisualPlan;
use App\Models\VisualPlanItem;
use App\Services\Assets\AssetFulfillmentQualityService;
use App\Services\Assets\AssetProductionHandoffService;
use App\Services\Assets\AssetProductionManifestService;
use App\Services\VisualPlan\VisualPlanQualityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssetProductionHandoffTest extends TestCase
{
    use RefreshDatabase;

    private AssetProductionHandoffService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AssetProductionHandoffService::class);
    }

    #[Test]
    public function a_plan_with_no_requirements_is_not_ready(): void
    {
        $user = User::factory()->create();
        $plan = $this->planFor($this->projectFor($user));

        $result = $this->service->evaluate($plan);

        $this->assertFalse($result['ready']);
        $this->assertSame(0, $result['summary']['production_entries']);
        $this->assertGreaterThan(0, $result['summary']['total_blockers']);

        $sources = array_column($result['issues'], 'source');
        $this->assertContains('planning', $sources);
        $this->assertContains('asset_readiness', $sources);
        $this->assertContains('production_manifest', $sources);

        $codes = array_column($result['issues'], 'code');
        $this->assertContains('NO_ASSET_REQUIREMENTS', $codes);
    }

    #[Test]
    public function a_planning_blocker_blocks_the_handoff(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);

        // An item with no requirement is a planning blocker.
        $this->itemFor($plan);

        $result = $this->service->evaluate($plan);

        $this->assertFalse($result['ready']);
        $this->assertGreaterThan(0, $result['summary']['planning_blockers']);

        $planning = array_filter($result['issues'], fn (array $issue) => $issue['source'] === 'planning');
        $this->assertNotEmpty($planning);
    }

    #[Test]
    public function an_asset_readiness_blocker_blocks_the_handoff(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => AssetRequirementStatus::Searching,
            'selected_asset_id' => null,
        ]);

        $result = $this->service->evaluate($plan);

        $this->assertFalse($result['ready']);
        $this->assertSame(1, $result['summary']['asset_blockers']);

        $codes = array_column($result['issues'], 'code');
        $this->assertContains('REQUIREMENT_NOT_FULFILLED', $codes);

        $assetIssues = array_filter($result['issues'], fn (array $issue) => $issue['source'] === 'asset_readiness');
        $this->assertNotEmpty($assetIssues);
    }

    #[Test]
    public function a_manifest_blocker_blocks_the_handoff(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        $candidate = $this->verifiedVideo($project);
        $outsider = $this->verifiedVideo($project);

        $requirement = $this->fulfilledRequirement($item, $outsider);
        $requirement->assets()->detach($outsider->id);
        $requirement->assets()->attach($candidate->id);

        $result = $this->service->evaluate($plan);

        $this->assertFalse($result['ready']);
        $this->assertGreaterThan(0, $result['summary']['manifest_blockers']);

        $manifestIssues = array_filter($result['issues'], fn (array $issue) => $issue['source'] === 'production_manifest');
        $this->assertNotEmpty($manifestIssues);
    }

    #[Test]
    public function a_fully_valid_plan_is_ready_with_no_issues(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedVideo($project, ['duration_seconds' => 10]);

        $this->fulfilledRequirement($item, $asset, targetDuration: 10);

        $result = $this->service->evaluate($plan);

        $this->assertTrue($result['ready']);
        $this->assertSame(0, $result['summary']['total_blockers']);
        $this->assertSame(0, $result['summary']['total_warnings']);
        $this->assertSame(1, $result['summary']['production_entries']);
        $this->assertSame([], $result['issues']);
    }

    #[Test]
    public function warnings_alone_do_not_block_the_handoff(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedVideo($project, ['duration_seconds' => 30]);

        $this->fulfilledRequirement($item, $asset, targetDuration: 5);

        $result = $this->service->evaluate($plan);

        $this->assertTrue($result['ready']);
        $this->assertSame(0, $result['summary']['total_blockers']);
        $this->assertGreaterThan(0, $result['summary']['total_warnings']);

        $codes = array_column($result['issues'], 'code');
        $this->assertContains('DURATION_EXCEEDS_TARGET', $codes);
    }

    #[Test]
    public function production_entries_come_from_the_manifest(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);

        foreach ([1, 2] as $order) {
            $item = $this->itemFor($plan, order: $order);
            $asset = $this->verifiedVideo($project, ['file_name' => "asset-{$order}.mp4"]);
            $this->fulfilledRequirement($item, $asset);
        }

        $handoff = $this->service->evaluate($plan);
        $manifest = app(AssetProductionManifestService::class)->build($plan->fresh());

        $this->assertSame($manifest['summary']['valid_entries'], $handoff['summary']['production_entries']);
        $this->assertSame(2, $handoff['summary']['production_entries']);
    }

    #[Test]
    public function issues_are_aggregated_in_a_deterministic_source_order(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => AssetRequirementStatus::Searching,
            'selected_asset_id' => null,
        ]);

        $result = $this->service->evaluate($plan);

        $sources = [];
        foreach ($result['issues'] as $issue) {
            if (! in_array($issue['source'], $sources, true)) {
                $sources[] = $issue['source'];
            }
        }

        $this->assertSame(['planning', 'asset_readiness', 'production_manifest'], $sources);
    }

    #[Test]
    public function summary_counts_match_the_upstream_layers(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => AssetRequirementStatus::Searching,
            'selected_asset_id' => null,
        ]);

        $handoff = $this->service->evaluate($plan);

        $planning = app(VisualPlanQualityService::class)->evaluateVisualPlan($plan->fresh());
        $assets = app(AssetFulfillmentQualityService::class)->evaluateVisualPlan($plan->fresh());

        $this->assertSame(count($planning['blockers']), $handoff['summary']['planning_blockers']);
        $this->assertSame(count($assets['blockers']), $handoff['summary']['asset_blockers']);
        $expectedWarnings = count($planning['warnings']) + count($assets['warnings']);
        $this->assertSame($expectedWarnings, $handoff['summary']['total_warnings']);
    }

    #[Test]
    public function it_does_not_invent_new_validation_codes(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => AssetRequirementStatus::Searching,
            'selected_asset_id' => null,
        ]);

        $handoff = $this->service->evaluate($plan);

        $planning = app(VisualPlanQualityService::class)->evaluateVisualPlan($plan->fresh());
        $assets = app(AssetFulfillmentQualityService::class)->evaluateVisualPlan($plan->fresh());
        $manifest = app(AssetProductionManifestService::class)->build($plan->fresh());

        $upstreamCodes = array_merge(
            array_column($planning['blockers'], 'code'),
            array_column($planning['warnings'], 'code'),
            array_column($assets['blockers'], 'code'),
            array_column($assets['warnings'], 'code'),
            array_column($manifest['issues'], 'code'),
        );

        $handoffCodes = array_column($handoff['issues'], 'code');

        $this->assertCount(count($upstreamCodes), $handoffCodes);
        foreach ($handoffCodes as $code) {
            $this->assertContains($code, $upstreamCodes);
        }
    }

    #[Test]
    public function evaluating_the_handoff_is_read_only(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedVideo($project);
        $requirement = $this->fulfilledRequirement($item, $asset);

        $snapshot = [
            'status' => $requirement->fresh()->status->value,
            'selected_asset_id' => $requirement->fresh()->selected_asset_id,
            'assets' => Asset::count(),
            'requirements' => AssetRequirement::count(),
            'plan_status' => $plan->fresh()->status->value,
        ];

        $this->service->evaluate($plan);
        $this->service->evaluate($plan->fresh());

        $after = [
            'status' => $requirement->fresh()->status->value,
            'selected_asset_id' => $requirement->fresh()->selected_asset_id,
            'assets' => Asset::count(),
            'requirements' => AssetRequirement::count(),
            'plan_status' => $plan->fresh()->status->value,
        ];

        $this->assertSame($snapshot, $after);
    }

    #[Test]
    public function evaluating_the_handoff_does_not_issue_a_query_per_requirement(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);

        foreach (range(1, 5) as $order) {
            $item = $this->itemFor($plan, order: $order);
            $asset = $this->verifiedVideo($project, ['file_name' => "asset-{$order}.mp4"]);
            $this->fulfilledRequirement($item, $asset);
        }

        $plan = $plan->fresh();

        DB::enableQueryLog();
        $result = $this->service->evaluate($plan);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertTrue($result['ready']);
        $this->assertSame(5, $result['summary']['production_entries']);
        $this->assertLessThanOrEqual(10, count($queries), json_encode(array_column($queries, 'query')));
    }

    private function projectFor(User $user): ContentProject
    {
        return ContentProject::factory()->for($user, 'creator')->create();
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

    private function itemFor(VisualPlan $plan, int $order = 1): VisualPlanItem
    {
        return $plan->items()->create([
            'order' => $order,
            'section' => 'body',
            'narration_text' => 'Cats purr to communicate with humans.',
            'visual_type' => VisualPlanItemType::AnimalClip,
            'visual_prompt' => 'Close up of a cat purring on a blanket.',
            'duration_seconds' => 5,
            'notes' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function verifiedVideo(ContentProject $project, array $overrides = []): Asset
    {
        return Asset::factory()->withVerifiedFile()->create(array_merge([
            'content_project_id' => $project->id,
            'type' => AssetType::Video,
            'file_name' => 'cat-walking.mp4',
            'duration_seconds' => 10,
            'width' => 1080,
            'height' => 1920,
            'aspect_ratio' => 0.5625,
        ], $overrides));
    }

    private function fulfilledRequirement(
        VisualPlanItem $item,
        Asset $asset,
        ?int $targetDuration = 5
    ): AssetRequirement {
        $requirement = AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'requirement_type' => AssetRequirementType::Video,
            'aspect_ratio' => AssetRequirementAspectRatio::Portrait916,
            'target_duration_seconds' => $targetDuration,
            'status' => AssetRequirementStatus::Fulfilled,
            'selected_asset_id' => $asset->id,
        ]);

        $requirement->assets()->attach($asset->id);

        return $requirement;
    }
}
