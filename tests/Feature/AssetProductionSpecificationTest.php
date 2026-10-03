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
use App\Services\Assets\AssetProductionHandoffService;
use App\Services\Assets\AssetProductionManifestService;
use App\Services\Assets\AssetProductionSpecificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssetProductionSpecificationTest extends TestCase
{
    use RefreshDatabase;

    private AssetProductionSpecificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AssetProductionSpecificationService::class);
    }

    #[Test]
    public function an_empty_plan_has_no_segments_and_is_not_ready(): void
    {
        $user = User::factory()->create();
        $plan = $this->planFor($this->projectFor($user));

        $result = $this->service->evaluate($plan);

        $this->assertFalse($result['ready']);
        $this->assertSame(0, $result['specification']['segment_count']);
        $this->assertSame(0, $result['specification']['total_target_duration_seconds']);
        $this->assertSame(0, $result['specification']['total_planned_duration_seconds']);
        $this->assertSame([], $result['segments']);

        $codes = array_column($result['issues'], 'code');
        $this->assertContains('NO_ASSET_REQUIREMENTS', $codes);
    }

    #[Test]
    public function a_fully_valid_plan_produces_an_ordered_specification(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan, duration: 5);
        $asset = $this->verifiedVideo($project, ['duration_seconds' => 10]);

        $this->fulfilledRequirement($item, $asset, targetDuration: 10);

        $result = $this->service->evaluate($plan);

        $this->assertTrue($result['ready']);
        $this->assertSame(1, $result['specification']['segment_count']);
        $this->assertSame(10, $result['specification']['total_target_duration_seconds']);
        $this->assertSame(5, $result['specification']['total_planned_duration_seconds']);
        $this->assertSame([], $result['issues']);

        $segment = $result['segments'][0];
        $this->assertSame($item->id, $segment['visual_plan_item_id']);
        $this->assertSame($item->order, $segment['visual_plan_item_order']);
        $this->assertSame($asset->id, $segment['asset_id']);
        $this->assertSame(10, $segment['duration_seconds']);
        $this->assertSame(10, $segment['target_duration_seconds']);
        $this->assertSame(5, $segment['planned_duration_seconds']);
    }

    #[Test]
    public function segments_are_ordered_by_item_order_not_insertion_order(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);

        $second = $this->itemFor($plan, order: 2, duration: 4);
        $first = $this->itemFor($plan, order: 1, duration: 6);

        $this->fulfilledRequirement($second, $this->verifiedVideo($project, ['file_name' => 'b.mp4']));
        $this->fulfilledRequirement($first, $this->verifiedVideo($project, ['file_name' => 'a.mp4']));

        $result = $this->service->evaluate($plan);

        $orders = array_column($result['segments'], 'visual_plan_item_order');
        $this->assertSame([1, 2], $orders);
        $this->assertSame(10, $result['specification']['total_planned_duration_seconds']);
    }

    #[Test]
    public function an_unfulfilled_requirement_produces_no_segment(): void
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
        $this->assertSame([], $result['segments']);
        $this->assertSame(0, $result['specification']['segment_count']);

        $codes = array_column($result['issues'], 'code');
        $this->assertContains('REQUIREMENT_NOT_FULFILLED', $codes);
    }

    #[Test]
    public function a_cross_project_selected_asset_produces_no_segment_and_leaks_nothing(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $otherProject = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $foreign = $this->verifiedVideo($otherProject, ['file_name' => 'secret.mp4']);

        $this->fulfilledRequirement($item, $foreign);

        $result = $this->service->evaluate($plan);

        $this->assertFalse($result['ready']);
        $this->assertSame([], $result['segments']);

        $codes = array_column($result['issues'], 'code');
        $this->assertContains('SELECTED_ASSET_CROSS_PROJECT', $codes);
        $this->assertStringNotContainsString('secret.mp4', json_encode($result));
    }

    #[Test]
    public function warnings_do_not_block_the_specification(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan, duration: 5);
        $asset = $this->verifiedVideo($project, ['duration_seconds' => 30]);

        $this->fulfilledRequirement($item, $asset, targetDuration: 5);

        $result = $this->service->evaluate($plan);

        $this->assertTrue($result['ready']);
        $this->assertSame(1, $result['specification']['segment_count']);

        $codes = array_column($result['issues'], 'code');
        $this->assertContains('DURATION_EXCEEDS_TARGET', $codes);
    }

    #[Test]
    public function segments_mirror_the_manifest_valid_entries(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);

        foreach ([1, 2] as $order) {
            $item = $this->itemFor($plan, order: $order);
            $this->fulfilledRequirement($item, $this->verifiedVideo($project, ['file_name' => "asset-{$order}.mp4"]));
        }

        $specification = $this->service->evaluate($plan);
        $manifest = app(AssetProductionManifestService::class)->build($plan->fresh());

        $this->assertSame(
            array_column($manifest['entries'], 'asset_requirement_id'),
            array_column($specification['segments'], 'asset_requirement_id')
        );
        $this->assertSame(count($manifest['entries']), $specification['specification']['segment_count']);
    }

    #[Test]
    public function issues_are_the_handoff_issues_unchanged(): void
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

        $specification = $this->service->evaluate($plan);
        $handoff = app(AssetProductionHandoffService::class)->evaluate($plan->fresh());

        $this->assertSame($handoff['ready'], $specification['ready']);
        $this->assertSame($handoff['issues'], $specification['issues']);
    }

    #[Test]
    public function evaluating_the_specification_is_read_only(): void
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
    public function evaluating_the_specification_does_not_issue_a_query_per_requirement(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);

        foreach (range(1, 5) as $order) {
            $item = $this->itemFor($plan, order: $order);
            $this->fulfilledRequirement($item, $this->verifiedVideo($project, ['file_name' => "asset-{$order}.mp4"]));
        }

        $plan = $plan->fresh();

        DB::enableQueryLog();
        $result = $this->service->evaluate($plan);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertTrue($result['ready']);
        $this->assertSame(5, $result['specification']['segment_count']);
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

    private function itemFor(VisualPlan $plan, int $order = 1, int $duration = 5): VisualPlanItem
    {
        return $plan->items()->create([
            'order' => $order,
            'section' => 'body',
            'narration_text' => 'Cats purr to communicate with humans.',
            'visual_type' => VisualPlanItemType::AnimalClip,
            'visual_prompt' => 'Close up of a cat purring on a blanket.',
            'duration_seconds' => $duration,
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
