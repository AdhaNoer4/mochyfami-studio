<?php

namespace Tests\Feature;

use App\Enums\AssetRequirementAspectRatio;
use App\Enums\AssetRequirementStatus;
use App\Enums\AssetRequirementType;
use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\ScriptStatus;
use App\Enums\VisualPlanItemType;
use App\Enums\VisualPlanStatus;
use App\Models\Asset;
use App\Models\AssetRequirement;
use App\Models\ContentProject;
use App\Models\Script;
use App\Models\User;
use App\Models\VisualPlan;
use App\Models\VisualPlanItem;
use App\Services\Assets\AssetProductionManifestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssetProductionManifestTest extends TestCase
{
    use RefreshDatabase;

    private AssetProductionManifestService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AssetProductionManifestService::class);
    }

    #[Test]
    public function a_plan_with_no_requirements_is_not_ready_and_reports_no_asset_requirements(): void
    {
        $user = User::factory()->create();
        $plan = $this->planFor($this->projectFor($user));

        $result = $this->service->build($plan);

        $this->assertFalse($result['ready']);
        $this->assertSame(0, $result['summary']['total_requirements']);
        $this->assertSame(0, $result['summary']['valid_entries']);
        $this->assertSame(0, $result['summary']['invalid_requirements']);
        $this->assertSame([], $result['entries']);
        $this->assertSame('NO_ASSET_REQUIREMENTS', $result['issues'][0]['code']);
        $this->assertSame('blocker', $result['issues'][0]['severity']);
    }

    #[Test]
    public function a_fulfilled_valid_requirement_produces_one_entry(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedVideo($project);

        $requirement = $this->fulfilledRequirement($item, $asset);

        $result = $this->service->build($plan);

        $this->assertTrue($result['ready']);
        $this->assertSame(1, $result['summary']['total_requirements']);
        $this->assertSame(1, $result['summary']['valid_entries']);
        $this->assertSame(0, $result['summary']['invalid_requirements']);
        $this->assertCount(1, $result['entries']);

        $entry = $result['entries'][0];
        $this->assertSame($item->id, $entry['visual_plan_item_id']);
        $this->assertSame(1, $entry['visual_plan_item_order']);
        $this->assertSame($requirement->id, $entry['asset_requirement_id']);
        $this->assertSame('video', $entry['requirement_type']);
        $this->assertSame($asset->id, $entry['asset_id']);
        $this->assertSame('video', $entry['asset_type']);
        $this->assertSame('cat-walking.mp4', $entry['file_name']);
        $this->assertSame('video/mp4', $entry['mime_type']);
        $this->assertSame(10, $entry['duration_seconds']);
        $this->assertSame(1080, $entry['width']);
        $this->assertSame(1920, $entry['height']);
        $this->assertSame(0.5625, $entry['aspect_ratio']);
        $this->assertSame(5, $entry['target_duration_seconds']);
        $this->assertSame('fulfilled', $entry['status']);
    }

    #[Test]
    public function entries_never_expose_a_file_path_or_public_url(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedVideo($project);

        $this->fulfilledRequirement($item, $asset);

        $result = $this->service->build($plan);

        $this->assertArrayNotHasKey('file_path', $result['entries'][0]);
        $this->assertArrayNotHasKey('preview_url', $result['entries'][0]);
        $this->assertArrayNotHasKey('url', $result['entries'][0]);
    }

    #[Test]
    public function multiple_valid_requirements_produce_multiple_entries(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        $first = $this->verifiedVideo($project, ['file_name' => 'first.mp4']);
        $second = $this->verifiedVideo($project, ['file_name' => 'second.mp4']);

        $this->fulfilledRequirement($item, $first);
        $this->fulfilledRequirement($item, $second);

        $result = $this->service->build($plan);

        $this->assertTrue($result['ready']);
        $this->assertCount(2, $result['entries']);
        $this->assertSame('first.mp4', $result['entries'][0]['file_name']);
        $this->assertSame('second.mp4', $result['entries'][1]['file_name']);
    }

    #[Test]
    public function only_the_selected_candidate_becomes_an_entry(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        $candidateA = $this->verifiedVideo($project, ['file_name' => 'a.mp4']);
        $candidateB = $this->verifiedVideo($project, ['file_name' => 'b.mp4']);
        $selected = $this->verifiedVideo($project, ['file_name' => 'c.mp4']);

        $requirement = $this->fulfilledRequirement($item, $selected);
        $requirement->assets()->attach([$candidateA->id, $candidateB->id]);

        $result = $this->service->build($plan);

        $this->assertTrue($result['ready']);
        $this->assertCount(1, $result['entries']);
        $this->assertSame($selected->id, $result['entries'][0]['asset_id']);
        $this->assertSame('c.mp4', $result['entries'][0]['file_name']);
    }

    #[Test]
    public function a_selected_asset_that_is_not_a_candidate_is_invalid(): void
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

        $result = $this->service->build($plan);

        $this->assertFalse($result['ready']);
        $this->assertSame([], $result['entries']);
        $this->assertSame('SELECTED_ASSET_NOT_CANDIDATE', $result['issues'][0]['code']);
    }

    #[Test]
    public function an_unfulfilled_requirement_is_invalid(): void
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

        $result = $this->service->build($plan);

        $this->assertFalse($result['ready']);
        $this->assertSame(1, $result['summary']['invalid_requirements']);
        $this->assertSame([], $result['entries']);
        $this->assertSame('REQUIREMENT_NOT_FULFILLED', $result['issues'][0]['code']);
    }

    #[Test]
    public function a_selected_but_unfulfilled_requirement_is_invalid(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedVideo($project);

        AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => AssetRequirementStatus::Searching,
            'selected_asset_id' => $asset->id,
        ]);

        $result = $this->service->build($plan);

        $this->assertFalse($result['ready']);
        $this->assertSame('REQUIREMENT_SELECTED_BUT_NOT_FULFILLED', $result['issues'][0]['code']);
    }

    #[Test]
    public function a_fulfilled_requirement_without_a_selection_is_invalid(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => AssetRequirementStatus::Fulfilled,
            'selected_asset_id' => null,
        ]);

        $result = $this->service->build($plan);

        $this->assertFalse($result['ready']);
        $this->assertSame('FULFILLED_REQUIREMENT_WITHOUT_SELECTION', $result['issues'][0]['code']);
    }

    #[Test]
    public function a_selected_asset_that_no_longer_exists_is_invalid(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedVideo($project);

        $requirement = $this->fulfilledRequirement($item, $asset);

        Schema::disableForeignKeyConstraints();
        AssetRequirement::whereKey($requirement->id)->update(['selected_asset_id' => 999999]);
        Schema::enableForeignKeyConstraints();

        $result = $this->service->build($plan->fresh());

        $this->assertFalse($result['ready']);
        $this->assertSame([], $result['entries']);
        $this->assertSame('SELECTED_ASSET_MISSING', $result['issues'][0]['code']);
    }

    #[Test]
    public function a_selected_asset_from_another_project_is_invalid_and_its_metadata_is_not_exposed(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $otherProject = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        $foreign = $this->verifiedVideo($otherProject, ['file_name' => 'secret.mp4']);
        $this->fulfilledRequirement($item, $foreign);

        $result = $this->service->build($plan);

        $this->assertFalse($result['ready']);
        $this->assertSame([], $result['entries']);
        $this->assertSame('SELECTED_ASSET_CROSS_PROJECT', $result['issues'][0]['code']);
        $this->assertStringNotContainsString('secret.mp4', json_encode($result));
    }

    #[Test]
    public function a_selected_asset_without_a_file_is_invalid(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        $fileless = Asset::factory()->create([
            'content_project_id' => $project->id,
            'type' => AssetType::Video,
            'status' => AssetStatus::Available,
            'file_path' => null,
        ]);

        $this->fulfilledRequirement($item, $fileless);

        $result = $this->service->build($plan);

        $this->assertFalse($result['ready']);
        $this->assertSame('SELECTED_ASSET_HAS_NO_FILE', $result['issues'][0]['code']);
    }

    #[Test]
    public function an_incompatible_selected_asset_is_invalid_and_reports_compatibility_details(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        $audio = Asset::factory()->withVerifiedFile()->create([
            'content_project_id' => $project->id,
            'type' => AssetType::Audio,
            'duration_seconds' => 10,
        ]);

        $this->fulfilledRequirement($item, $audio);

        $result = $this->service->build($plan);

        $this->assertFalse($result['ready']);
        $this->assertSame([], $result['entries']);
        $this->assertSame('SELECTED_ASSET_INCOMPATIBLE', $result['issues'][0]['code']);

        $codes = array_column($result['issues'], 'code');
        $this->assertContains('TYPE_MISMATCH', $codes);
    }

    #[Test]
    public function multiple_invalid_requirements_are_counted_and_reported_in_order(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => AssetRequirementStatus::Pending,
            'selected_asset_id' => null,
        ]);
        AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => AssetRequirementStatus::Fulfilled,
            'selected_asset_id' => null,
        ]);

        $result = $this->service->build($plan);

        $this->assertFalse($result['ready']);
        $this->assertSame(2, $result['summary']['total_requirements']);
        $this->assertSame(0, $result['summary']['valid_entries']);
        $this->assertSame(2, $result['summary']['invalid_requirements']);
        $this->assertSame('REQUIREMENT_NOT_FULFILLED', $result['issues'][0]['code']);
        $this->assertSame('FULFILLED_REQUIREMENT_WITHOUT_SELECTION', $result['issues'][1]['code']);
    }

    #[Test]
    public function entries_are_ordered_by_item_order_then_requirement_id(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);

        $second = $this->itemFor($plan, order: 2);
        $first = $this->itemFor($plan, order: 1);

        $assetA = $this->verifiedVideo($project, ['file_name' => 'a.mp4']);
        $assetB = $this->verifiedVideo($project, ['file_name' => 'b.mp4']);

        $this->fulfilledRequirement($second, $assetA, file: 'late.mp4');
        $this->fulfilledRequirement($first, $assetB, file: 'early.mp4');

        $result = $this->service->build($plan);

        $this->assertTrue($result['ready']);
        $this->assertSame([1, 2], array_column($result['entries'], 'visual_plan_item_order'));
        $this->assertSame('early.mp4', $result['entries'][0]['file_name']);
        $this->assertSame('late.mp4', $result['entries'][1]['file_name']);
    }

    #[Test]
    public function a_valid_requirement_with_a_warning_still_produces_an_entry(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedVideo($project, ['duration_seconds' => 30]);

        $this->fulfilledRequirement($item, $asset, targetDuration: 5);

        $result = $this->service->build($plan);

        $this->assertTrue($result['ready']);
        $this->assertCount(1, $result['entries']);
        $codes = array_column($result['issues'], 'code');
        $this->assertContains('DURATION_EXCEEDS_TARGET', $codes);
        $this->assertSame('warning', $result['issues'][array_search('DURATION_EXCEEDS_TARGET', $codes, true)]['severity']);
    }

    #[Test]
    public function building_the_manifest_is_read_only(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedVideo($project);
        $requirement = $this->fulfilledRequirement($item, $asset);

        $before = [
            'status' => $requirement->fresh()->status->value,
            'selected_asset_id' => $requirement->fresh()->selected_asset_id,
            'assets' => Asset::count(),
            'requirements' => AssetRequirement::count(),
        ];

        $this->service->build($plan);
        $this->service->build($plan->fresh());

        $after = [
            'status' => $requirement->fresh()->status->value,
            'selected_asset_id' => $requirement->fresh()->selected_asset_id,
            'assets' => Asset::count(),
            'requirements' => AssetRequirement::count(),
        ];

        $this->assertSame($before, $after);
    }

    #[Test]
    public function building_the_manifest_does_not_issue_a_query_per_requirement(): void
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
        $result = $this->service->build($plan);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertTrue($result['ready']);
        $this->assertCount(5, $result['entries']);
        $this->assertLessThanOrEqual(5, count($queries), json_encode(array_column($queries, 'query')));
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

        $script->update(['current_version_id' => $versionModel->id, 'status' => ScriptStatus::Review]);
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
        ?string $file = null,
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

        if ($file !== null) {
            $asset->update(['file_name' => $file]);
        }

        return $requirement;
    }
}
