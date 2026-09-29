<?php

namespace Tests\Feature;

use App\Enums\AssetRequirementAspectRatio;
use App\Enums\AssetRequirementStatus;
use App\Enums\AssetRequirementType;
use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Enums\ResearchStatus;
use App\Enums\ScriptStatus;
use App\Enums\VisualPlanItemType;
use App\Enums\VisualPlanStatus;
use App\Exceptions\InvalidAssetRequirementStatusTransitionException;
use App\Models\AssetRequirement;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Script;
use App\Models\Source;
use App\Models\User;
use App\Models\VisualPlan;
use App\Models\VisualPlanItem;
use App\Services\AssetRequirementService;
use App\Services\VisualPlanService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class AssetRequirementTest extends TestCase
{
    use RefreshDatabase;

    private function readyProject(User $user): ContentProject
    {
        $project = ContentProject::factory()->for($user, 'creator')->create();
        $report = ResearchReport::factory()->create([
            'content_project_id' => $project->id,
            'status' => ResearchStatus::Completed,
        ]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claims = [
            ['claim' => 'Cats purr to communicate with humans'],
            ['claim' => 'Purring helps cats soothe themselves when stressed'],
        ];
        foreach ($claims as $claimData) {
            $claim = ResearchClaim::factory()->create([
                'research_report_id' => $report->id,
                'claim' => $claimData['claim'],
                'status' => ResearchClaimStatus::Supported,
                'importance' => ResearchClaimImportance::High,
            ]);
            $claim->sources()->attach($source->id);
        }

        return $project;
    }

    private function scriptFor(ContentProject $project, int $version = 1): Script
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

    private function itemFor(VisualPlan $plan, array $state = []): VisualPlanItem
    {
        return $plan->items()->create(array_merge([
            'order' => 1,
            'section' => 'body',
            'narration_text' => 'Cats purr to communicate with humans.',
            'visual_type' => VisualPlanItemType::AnimalClip,
            'visual_prompt' => 'Close up of a cat purring on a blanket.',
            'duration_seconds' => 5,
            'notes' => null,
        ], $state));
    }

    private function service(): AssetRequirementService
    {
        return app(AssetRequirementService::class);
    }

    #[Test]
    public function asset_requirement_belongs_to_the_visual_plan_item(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        $requirement = AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
        ]);

        $this->assertSame($item->id, $requirement->visualPlanItem->id);
        $this->assertSame($requirement->id, $item->assetRequirements()->first()->id);
    }

    #[Test]
    public function deleting_a_visual_plan_item_cascades_to_its_asset_requirements(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $requirement = AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
        ]);

        $item->delete();

        $this->assertDatabaseMissing('asset_requirements', ['id' => $requirement->id]);
    }

    #[Test]
    public function a_single_item_can_hold_more_than_one_requirement(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        AssetRequirement::factory()->count(2)->create(['visual_plan_item_id' => $item->id]);

        $this->assertSame(2, $item->assetRequirements()->count());
    }

    #[Test]
    public function creating_a_requirement_always_starts_as_pending(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        $requirement = $this->service()->createRequirement($project, 1, $item->id, [
            'requirement_type' => AssetRequirementType::Video->value,
            'description' => 'A calm cat purring.',
        ]);

        $this->assertSame(AssetRequirementStatus::Pending, $requirement->status);
    }

    #[Test]
    public function listing_requirements_scopes_to_the_given_item(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $otherItem = $this->itemFor($plan, ['order' => 2]);

        AssetRequirement::factory()->create(['visual_plan_item_id' => $item->id]);
        AssetRequirement::factory()->create(['visual_plan_item_id' => $otherItem->id]);

        $requirements = $this->service()->listRequirements($project, 1, $item->id);

        $this->assertCount(1, $requirements);
        $this->assertSame($item->id, $requirements->first()->visual_plan_item_id);
    }

    #[Test]
    public function listing_requirements_for_an_item_of_another_project_throws(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $otherProject = $this->readyProject($user);
        $otherPlan = $this->planFor($otherProject);
        $otherItem = $this->itemFor($otherPlan);

        $this->expectException(ModelNotFoundException::class);

        $this->service()->listRequirements($project, 1, $otherItem->id);
    }

    #[Test]
    public function listing_requirements_without_a_visual_plan_throws(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->scriptFor($project);

        $this->expectException(ModelNotFoundException::class);

        $this->service()->listRequirements($project, 1, 1);
    }

    #[Test]
    public function updating_a_requirement_does_not_change_its_status(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $requirement = AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => AssetRequirementStatus::Searching,
        ]);

        $updated = $this->service()->updateRequirement($project, 1, $item->id, $requirement->id, [
            'description' => 'Rewritten description.',
        ]);

        $this->assertSame('Rewritten description.', $updated->description);
        $this->assertSame(AssetRequirementStatus::Searching, $updated->status);
    }

    #[Test]
    public function updating_a_requirement_can_clear_an_optional_field(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $requirement = AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'notes' => 'Existing note.',
        ]);

        $updated = $this->service()->updateRequirement($project, 1, $item->id, $requirement->id, [
            'notes' => null,
        ]);

        $this->assertNull($updated->notes);
    }

    #[Test]
    public function updating_a_requirement_ignores_an_unrelated_requirement(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $otherItem = $this->itemFor($plan, ['order' => 2]);
        $requirement = AssetRequirement::factory()->create(['visual_plan_item_id' => $item->id]);
        AssetRequirement::factory()->create(['visual_plan_item_id' => $otherItem->id]);

        $this->expectException(ModelNotFoundException::class);

        $this->service()->updateRequirement($project, 1, $otherItem->id, $requirement->id, [
            'description' => 'Should never be written.',
        ]);
    }

    #[Test]
    public function deleting_a_requirement_leaves_its_siblings_alone(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $requirement = AssetRequirement::factory()->create(['visual_plan_item_id' => $item->id]);
        $sibling = AssetRequirement::factory()->create(['visual_plan_item_id' => $item->id]);

        $this->service()->deleteRequirement($project, 1, $item->id, $requirement->id);

        $this->assertDatabaseMissing('asset_requirements', ['id' => $requirement->id]);
        $this->assertDatabaseHas('asset_requirements', ['id' => $sibling->id]);
    }

    public static function visualTypeMappingProvider(): array
    {
        return [
            'animal clip becomes video' => [VisualPlanItemType::AnimalClip, AssetRequirementType::Video],
            'stock video becomes video' => [VisualPlanItemType::StockVideo, AssetRequirementType::Video],
            'b roll becomes video' => [VisualPlanItemType::BRoll, AssetRequirementType::Video],
            'screen recording becomes video' => [VisualPlanItemType::ScreenRecording, AssetRequirementType::Video],
            'photo becomes image' => [VisualPlanItemType::Photo, AssetRequirementType::Image],
            'graphic becomes graphic' => [VisualPlanItemType::Graphic, AssetRequirementType::Graphic],
            'text becomes graphic' => [VisualPlanItemType::Text, AssetRequirementType::Graphic],
            'other becomes other' => [VisualPlanItemType::Other, AssetRequirementType::Other],
        ];
    }

    #[Test]
    #[DataProvider('visualTypeMappingProvider')]
    public function generation_maps_every_visual_type(VisualPlanItemType $visualType, AssetRequirementType $expected): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan, ['visual_type' => $visualType]);

        $this->service()->generateFromVisualPlan($project, 1);

        $this->assertSame($expected, $item->assetRequirements()->first()->requirement_type);
    }

    #[Test]
    public function generation_creates_one_requirement_per_item(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $this->itemFor($plan, ['order' => 1]);
        $this->itemFor($plan, ['order' => 2, 'visual_type' => VisualPlanItemType::Photo]);
        $this->itemFor($plan, ['order' => 3, 'visual_type' => VisualPlanItemType::Graphic]);

        $result = $this->service()->generateFromVisualPlan($project, 1);

        $this->assertSame(['created' => 3, 'existing' => 0, 'skipped' => 0], $result);
        $this->assertSame(3, $plan->items()->withCount('assetRequirements')->get()->sum('asset_requirements_count'));
    }

    #[Test]
    public function generation_never_calls_ai_or_any_provider(): void
    {
        Http::preventStrayRequests();

        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $this->itemFor($plan);

        $this->service()->generateFromVisualPlan($project, 1);

        $this->assertDatabaseCount('ai_generations', 0);
    }

    #[Test]
    public function generation_copies_the_visual_prompt_and_duration(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan, [
            'visual_prompt' => 'A cat stretching on a sunny windowsill.',
            'duration_seconds' => 7,
        ]);

        $this->service()->generateFromVisualPlan($project, 1);

        $requirement = $item->assetRequirements()->first();
        $this->assertSame('A cat stretching on a sunny windowsill.', $requirement->description);
        $this->assertSame(7, $requirement->target_duration_seconds);
    }

    #[Test]
    public function generation_defaults_the_aspect_ratio_to_portrait(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        $this->service()->generateFromVisualPlan($project, 1);

        $this->assertSame(AssetRequirementAspectRatio::Portrait916, $item->assetRequirements()->first()->aspect_ratio);
    }

    #[Test]
    public function generation_leaves_no_search_query_for_the_seeded_placeholder_prompt(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->scriptFor($project);
        $script->update(['status' => ScriptStatus::Review]);

        app(VisualPlanService::class)->createVisualPlanFromScript($project, 1);
        $item = $script->fresh()->currentVersion->visualPlan->items()->first();

        $this->service()->generateFromVisualPlan($project, 1);

        $this->assertNull($item->assetRequirements()->first()->search_query);
    }

    #[Test]
    public function generation_uses_the_visual_prompt_as_the_search_query(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan, ['visual_prompt' => '  A slow pan across a busy street.  ']);

        $this->service()->generateFromVisualPlan($project, 1);

        $this->assertSame('A slow pan across a busy street.', $item->assetRequirements()->first()->search_query);
    }

    #[Test]
    public function generation_skips_items_without_any_content(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan, ['narration_text' => '   ', 'visual_prompt' => '  ']);

        $result = $this->service()->generateFromVisualPlan($project, 1);

        $this->assertSame(['created' => 0, 'existing' => 0, 'skipped' => 1], $result);
        $this->assertSame(0, $item->assetRequirements()->count());
    }

    #[Test]
    public function repeated_generation_never_duplicates_requirements(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $this->itemFor($plan, ['order' => 1]);
        $this->itemFor($plan, ['order' => 2]);

        $first = $this->service()->generateFromVisualPlan($project, 1);
        $second = $this->service()->generateFromVisualPlan($project, 1);

        $this->assertSame(2, $first['created']);
        $this->assertSame(['created' => 0, 'existing' => 2, 'skipped' => 0], $second);
        $this->assertSame(2, $plan->items()->withCount('assetRequirements')->get()->sum('asset_requirements_count'));
    }

    #[Test]
    public function generation_preserves_a_manually_edited_requirement(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $manual = AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'requirement_type' => AssetRequirementType::Audio,
            'search_query' => 'soft purring loop',
            'status' => AssetRequirementStatus::Searching,
        ]);

        $this->service()->generateFromVisualPlan($project, 1);

        $this->assertSame(1, $item->assetRequirements()->count());
        $this->assertDatabaseHas('asset_requirements', [
            'id' => $manual->id,
            'requirement_type' => AssetRequirementType::Audio->value,
            'search_query' => 'soft purring loop',
            'status' => AssetRequirementStatus::Searching->value,
        ]);
    }

    #[Test]
    public function generation_rolls_back_everything_when_it_fails_part_way_through(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $this->itemFor($plan, ['order' => 1]);
        $this->itemFor($plan, ['order' => 2, 'visual_type' => VisualPlanItemType::Photo]);

        AssetRequirement::creating(function () {
            throw new RuntimeException('Generation blew up.');
        });

        try {
            $this->expectException(RuntimeException::class);
            $this->service()->generateFromVisualPlan($project, 1);
        } finally {
            $this->assertDatabaseCount('asset_requirements', 0);
        }
    }

    #[Test]
    public function generation_requires_a_visual_plan(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->scriptFor($project);

        $this->expectException(ModelNotFoundException::class);

        $this->service()->generateFromVisualPlan($project, 1);
    }

    #[Test]
    public function generation_never_touches_a_requirement_of_another_version(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project, 1);
        $item = $this->itemFor($plan);
        $this->scriptFor($project, 2);

        $this->expectException(ModelNotFoundException::class);

        $this->service()->generateFromVisualPlan($project, 2);
    }

    public static function allowedTransitionProvider(): array
    {
        return [
            'pending to searching' => [AssetRequirementStatus::Pending, AssetRequirementStatus::Searching],
            'pending to skipped' => [AssetRequirementStatus::Pending, AssetRequirementStatus::Skipped],
            'searching to fulfilled' => [AssetRequirementStatus::Searching, AssetRequirementStatus::Fulfilled],
            'searching to pending' => [AssetRequirementStatus::Searching, AssetRequirementStatus::Pending],
            'searching to skipped' => [AssetRequirementStatus::Searching, AssetRequirementStatus::Skipped],
            'skipped to pending' => [AssetRequirementStatus::Skipped, AssetRequirementStatus::Pending],
        ];
    }

    #[Test]
    #[DataProvider('allowedTransitionProvider')]
    public function status_can_move_along_the_allowed_edges(
        AssetRequirementStatus $from,
        AssetRequirementStatus $to
    ): void {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $requirement = AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => $from,
        ]);

        $updated = $this->service()->transitionStatus($project, 1, $item->id, $requirement->id, $to);

        $this->assertSame($to, $updated->status);
    }

    public static function forbiddenTransitionProvider(): array
    {
        return [
            'pending cannot be fulfilled directly' => [AssetRequirementStatus::Pending, AssetRequirementStatus::Fulfilled],
            'skipped cannot be fulfilled' => [AssetRequirementStatus::Skipped, AssetRequirementStatus::Fulfilled],
            'skipped cannot start searching' => [AssetRequirementStatus::Skipped, AssetRequirementStatus::Searching],
            'fulfilled is terminal' => [AssetRequirementStatus::Fulfilled, AssetRequirementStatus::Pending],
            'fulfilled cannot be skipped' => [AssetRequirementStatus::Fulfilled, AssetRequirementStatus::Skipped],
        ];
    }

    #[Test]
    #[DataProvider('forbiddenTransitionProvider')]
    public function status_rejects_a_move_outside_the_graph(
        AssetRequirementStatus $from,
        AssetRequirementStatus $to
    ): void {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $requirement = AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => $from,
        ]);

        try {
            $this->expectException(InvalidAssetRequirementStatusTransitionException::class);
            $this->service()->transitionStatus($project, 1, $item->id, $requirement->id, $to);
        } finally {
            $this->assertDatabaseHas('asset_requirements', [
                'id' => $requirement->id,
                'status' => $from->value,
            ]);
        }
    }

    #[Test]
    public function status_rejects_moving_to_the_current_status(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $requirement = AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => AssetRequirementStatus::Pending,
        ]);

        $this->expectException(InvalidAssetRequirementStatusTransitionException::class);

        $this->service()->transitionStatus(
            $project,
            1,
            $item->id,
            $requirement->id,
            AssetRequirementStatus::Pending
        );
    }

    #[Test]
    public function a_fulfilled_requirement_offers_no_further_transitions(): void
    {
        $this->assertSame([], AssetRequirementStatus::Fulfilled->allowedTransitions());
    }

    #[Test]
    public function generation_reports_what_it_did_for_the_user(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planFor($project);
        $this->itemFor($plan, ['order' => 1]);
        $this->itemFor($plan, ['order' => 2]);
        $this->itemFor($plan, ['order' => 3, 'narration_text' => ' ', 'visual_prompt' => ' ']);
        AssetRequirement::factory()->create(['visual_plan_item_id' => $plan->items()->where('order', 2)->first()->id]);

        $result = $this->service()->generateFromVisualPlan($project, 1);

        $this->assertSame(['created' => 1, 'existing' => 1, 'skipped' => 1], $result);
    }
}
