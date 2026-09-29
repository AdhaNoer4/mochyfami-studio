<?php

namespace Tests\Feature;

use App\Enums\AssetRequirementAspectRatio;
use App\Enums\AssetRequirementStatus;
use App\Enums\AssetRequirementType;
use App\Enums\ScriptStatus;
use App\Enums\VisualPlanItemType;
use App\Enums\VisualPlanStatus;
use App\Models\AssetRequirement;
use App\Models\ContentProject;
use App\Models\Script;
use App\Models\VisualPlan;
use App\Models\VisualPlanItem;
use App\Services\VisualPlan\VisualPlanQualityService;
use App\Services\VisualPlanService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VisualPlanQualityTest extends TestCase
{
    use RefreshDatabase;

    private function service(): VisualPlanQualityService
    {
        return app(VisualPlanQualityService::class);
    }

    private function scriptFor(ContentProject $project, int $version = 1): Script
    {
        $script = $project->script()->first() ?? Script::factory()->create([
            'content_project_id' => $project->id,
            'status' => ScriptStatus::Draft,
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

    private function requirementFor(VisualPlanItem $item, array $state = []): AssetRequirement
    {
        return $item->assetRequirements()->create(array_merge([
            'requirement_type' => AssetRequirementType::Video,
            'search_query' => 'cat purring close up',
            'description' => 'A cat purring on a blanket.',
            'target_duration_seconds' => 5,
            'aspect_ratio' => AssetRequirementAspectRatio::Portrait916,
            'status' => AssetRequirementStatus::Pending,
            'notes' => null,
        ], $state));
    }

    /**
     * A plan where every item is fully specified and every requirement is
     * valid, so the only remaining question is the plan status.
     */
    private function planThatIsReady(ContentProject $project, array $state = []): VisualPlan
    {
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $this->requirementFor($item);

        if ($state !== []) {
            $plan->update($state);
        }

        return $plan->fresh();
    }

    /**
     * @param  array<int, array<string, mixed>>  $issues
     * @return array<int, string>
     */
    private function codes(array $issues): array
    {
        return array_column($issues, 'code');
    }

    #[Test]
    public function a_fully_specified_plan_is_ready_with_a_full_score(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planThatIsReady($project);

        $result = $this->service()->evaluateVisualPlan($plan);

        $this->assertTrue($result['ready']);
        $this->assertSame(100, $result['score']);
        $this->assertSame([], $result['blockers']);
        $this->assertSame([], $result['warnings']);
    }

    #[Test]
    public function the_result_always_exposes_the_documented_shape(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);

        $result = $this->service()->evaluateVisualPlan($plan);

        $this->assertSame(
            ['ready', 'score', 'summary', 'blockers', 'warnings', 'info'],
            array_keys($result)
        );
        $this->assertIsBool($result['ready']);
        $this->assertIsInt($result['score']);
        $this->assertGreaterThanOrEqual(0, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);
    }

    #[Test]
    public function the_summary_counts_items_requirements_and_statuses(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);

        $withRequirement = $this->itemFor($plan);
        $this->requirementFor($withRequirement, ['status' => AssetRequirementStatus::Pending]);
        $this->requirementFor($withRequirement, ['status' => AssetRequirementStatus::Searching]);
        $this->requirementFor($withRequirement, ['status' => AssetRequirementStatus::Fulfilled]);
        $this->requirementFor($withRequirement, ['status' => AssetRequirementStatus::Skipped]);

        $this->itemFor($plan);

        $result = $this->service()->evaluateVisualPlan($plan);

        $this->assertSame(2, $result['summary']['total_items']);
        $this->assertSame(1, $result['summary']['items_with_requirements']);
        $this->assertSame(1, $result['summary']['items_without_requirements']);
        $this->assertSame(4, $result['summary']['total_requirements']);
        $this->assertSame(4, $result['summary']['valid_requirements']);
        $this->assertSame(0, $result['summary']['invalid_requirements']);
        $this->assertSame(1, $result['summary']['pending_requirements']);
        $this->assertSame(1, $result['summary']['searching_requirements']);
        $this->assertSame(1, $result['summary']['fulfilled_requirements']);
        $this->assertSame(1, $result['summary']['skipped_requirements']);
    }

    #[Test]
    public function an_empty_plan_is_blocked(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);

        $result = $this->service()->evaluateVisualPlan($plan);

        $this->assertFalse($result['ready']);
        $this->assertContains('EMPTY_VISUAL_PLAN', $this->codes($result['blockers']));
        $this->assertSame(0, $result['summary']['total_items']);
    }

    #[Test]
    public function an_archived_plan_is_blocked(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planThatIsReady($project, ['status' => VisualPlanStatus::Archived]);

        $result = $this->service()->evaluateVisualPlan($plan);

        $this->assertFalse($result['ready']);
        $this->assertContains('ARCHIVED_VISUAL_PLAN', $this->codes($result['blockers']));
    }

    #[Test]
    public function a_draft_plan_can_still_be_ready_but_is_reported_as_not_approved(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planThatIsReady($project, ['status' => VisualPlanStatus::Draft]);

        $result = $this->service()->evaluateVisualPlan($plan);

        $this->assertTrue($result['ready']);
        $this->assertContains('PLAN_NOT_APPROVED', $this->codes($result['info']));
        $this->assertContains('PLANNING_READY', $this->codes($result['info']));
    }

    #[Test]
    public function an_approved_plan_gets_no_not_approved_info(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planThatIsReady($project, ['status' => VisualPlanStatus::Approved]);

        $result = $this->service()->evaluateVisualPlan($plan);

        $this->assertTrue($result['ready']);
        $this->assertNotContains('PLAN_NOT_APPROVED', $this->codes($result['info']));
    }

    #[Test]
    public function planning_ready_is_absent_while_blockers_remain(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);

        $result = $this->service()->evaluateVisualPlan($plan);

        $this->assertNotContains('PLANNING_READY', $this->codes($result['info']));
    }

    #[Test]
    public function a_plan_whose_script_version_cannot_be_resolved_is_blocked(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);

        // The foreign key normally guarantees a version exists, so this branch
        // is only reachable defensively. Asserting it here proves the gate
        // reports an unresolvable reference instead of fataling on a null.
        $plan->setRelation('scriptVersion', null);

        $result = $this->service()->evaluateVisualPlan($plan);

        $this->assertFalse($result['ready']);
        $this->assertContains('INVALID_SCRIPT_VERSION_REFERENCE', $this->codes($result['blockers']));
    }

    #[Test]
    public function a_plan_bound_to_another_projects_script_version_is_blocked(): void
    {
        $project = ContentProject::factory()->create();
        $otherProject = ContentProject::factory()->create();
        $plan = $this->planFor($project);
        $otherScript = $this->scriptFor($otherProject);

        $plan->update(['script_version_id' => $otherScript->currentVersion->id]);

        $result = $this->service()->evaluateVisualPlan($plan->fresh());

        $this->assertFalse($result['ready']);
        $this->assertContains('INVALID_SCRIPT_VERSION_REFERENCE', $this->codes($result['blockers']));
    }

    #[Test]
    public function an_item_without_any_asset_requirement_is_blocked(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan, ['order' => 3]);

        $result = $this->service()->evaluateVisualPlan($plan);

        $this->assertFalse($result['ready']);
        $this->assertContains('ITEM_WITHOUT_ASSET_REQUIREMENT', $this->codes($result['blockers']));
        $this->assertSame(1, $result['summary']['items_without_requirements']);
    }

    #[Test]
    public function a_blocker_identifies_the_offending_item(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan, ['order' => 3]);

        $result = $this->service()->evaluateVisualPlan($plan);

        $blocker = collect($result['blockers'])
            ->firstWhere('code', 'ITEM_WITHOUT_ASSET_REQUIREMENT');

        $this->assertSame($item->id, $blocker['visual_plan_item_id']);
        $this->assertStringContainsString('#3', $blocker['message']);
        $this->assertSame('blocker', $blocker['severity']);
    }

    #[Test]
    public function every_visual_plan_item_type_requires_an_asset_requirement(): void
    {
        $project = ContentProject::factory()->create();

        foreach (VisualPlanItemType::cases() as $index => $visualType) {
            $plan = $this->planFor($project, $index + 1);
            $this->itemFor($plan, ['visual_type' => $visualType]);

            $result = $this->service()->evaluateVisualPlan($plan);

            $this->assertContains(
                'ITEM_WITHOUT_ASSET_REQUIREMENT',
                $this->codes($result['blockers']),
                "Visual type {$visualType->value} should still require an asset."
            );
        }
    }

    #[Test]
    public function an_item_whose_only_requirement_is_skipped_has_no_usable_requirement(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $this->requirementFor($item, ['status' => AssetRequirementStatus::Skipped]);

        $result = $this->service()->evaluateVisualPlan($plan);

        $this->assertFalse($result['ready']);
        $this->assertContains('NO_USABLE_ASSET_REQUIREMENT', $this->codes($result['blockers']));
    }

    #[Test]
    public function a_skipped_requirement_does_not_block_an_item_that_has_another_usable_one(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $this->requirementFor($item, ['status' => AssetRequirementStatus::Skipped]);
        $this->requirementFor($item, ['status' => AssetRequirementStatus::Pending]);

        $result = $this->service()->evaluateVisualPlan($plan);

        $this->assertTrue($result['ready']);
        $this->assertSame(1, $result['summary']['skipped_requirements']);
    }

    #[Test]
    public function a_fulfilled_requirement_still_leaves_the_plan_ready(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planThatIsReady($project);
        $requirement = $plan->items()->first()->assetRequirements()->first();
        $requirement->update(['status' => AssetRequirementStatus::Fulfilled]);

        $result = $this->service()->evaluateVisualPlan($plan->fresh());

        $this->assertTrue($result['ready']);
        $this->assertSame(1, $result['summary']['fulfilled_requirements']);
    }

    #[Test]
    public function a_requirement_with_an_empty_description_is_invalid(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $requirement = $this->requirementFor($item, ['description' => '   ']);

        $result = $this->service()->evaluateVisualPlan($plan);

        $this->assertFalse($result['ready']);
        $this->assertContains('INVALID_ASSET_REQUIREMENT', $this->codes($result['blockers']));
        $this->assertSame(1, $result['summary']['invalid_requirements']);
        $this->assertSame(
            $requirement->id,
            collect($result['blockers'])->firstWhere('code', 'INVALID_ASSET_REQUIREMENT')['asset_requirement_id']
        );
    }

    #[Test]
    public function a_requirement_still_holding_the_placeholder_prompt_is_blocked(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $this->requirementFor($item, [
            'description' => VisualPlanService::PLACEHOLDER_VISUAL_PROMPT,
        ]);

        $result = $this->service()->evaluateVisualPlan($plan);

        $this->assertFalse($result['ready']);
        $this->assertContains('PLACEHOLDER_VISUAL_DESCRIPTION', $this->codes($result['blockers']));
    }

    #[Test]
    public function a_requirement_with_a_non_positive_duration_is_invalid(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $this->requirementFor($item, ['target_duration_seconds' => 0]);

        $result = $this->service()->evaluateVisualPlan($plan);

        $this->assertFalse($result['ready']);
        $this->assertContains('INVALID_ASSET_REQUIREMENT', $this->codes($result['blockers']));
    }

    #[Test]
    public function a_corrupt_stored_enum_is_reported_instead_of_throwing(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $requirement = $this->requirementFor($item);

        DB::table('asset_requirements')->where('id', $requirement->id)->update([
            'requirement_type' => 'hologram',
            'status' => 'teleported',
        ]);

        $result = $this->service()->evaluateVisualPlan($plan->fresh());

        $this->assertFalse($result['ready']);
        $this->assertContains('INVALID_ASSET_REQUIREMENT', $this->codes($result['blockers']));
        $this->assertSame(1, $result['summary']['invalid_requirements']);
    }

    #[Test]
    public function a_corrupt_aspect_ratio_is_reported_instead_of_throwing(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $requirement = $this->requirementFor($item);

        DB::table('asset_requirements')->where('id', $requirement->id)->update([
            'aspect_ratio' => 'squircle',
        ]);

        $result = $this->service()->evaluateVisualPlan($plan->fresh());

        $this->assertFalse($result['ready']);
        $this->assertContains('INVALID_ASSET_REQUIREMENT', $this->codes($result['blockers']));
    }

    #[Test]
    public function a_searching_requirement_is_a_warning_not_a_blocker(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planThatIsReady($project);
        $requirement = $plan->items()->first()->assetRequirements()->first();
        $requirement->update(['status' => AssetRequirementStatus::Searching]);

        $result = $this->service()->evaluateVisualPlan($plan->fresh());

        $this->assertTrue($result['ready']);
        $this->assertContains('ASSET_REQUIREMENT_SEARCHING', $this->codes($result['warnings']));
        $this->assertSame([], $result['blockers']);
    }

    #[Test]
    public function a_searchable_requirement_without_a_search_query_is_a_warning(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planThatIsReady($project);
        $requirement = $plan->items()->first()->assetRequirements()->first();
        $requirement->update(['search_query' => null]);

        $result = $this->service()->evaluateVisualPlan($plan->fresh());

        $this->assertTrue($result['ready']);
        $this->assertContains('MISSING_ASSET_SEARCH_QUERY', $this->codes($result['warnings']));
    }

    #[Test]
    public function a_temporal_requirement_without_a_duration_is_a_warning(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planThatIsReady($project);
        $requirement = $plan->items()->first()->assetRequirements()->first();
        $requirement->update(['target_duration_seconds' => null]);

        $result = $this->service()->evaluateVisualPlan($plan->fresh());

        $this->assertTrue($result['ready']);
        $this->assertContains('MISSING_TARGET_DURATION', $this->codes($result['warnings']));
    }

    public static function nonTemporalRequirementTypes(): array
    {
        return [
            'image' => [AssetRequirementType::Image],
            'graphic' => [AssetRequirementType::Graphic],
            'screen recording' => [AssetRequirementType::ScreenRecording],
            'other' => [AssetRequirementType::Other],
        ];
    }

    #[DataProvider('nonTemporalRequirementTypes')]
    #[Test]
    public function a_non_temporal_requirement_needs_no_duration(AssetRequirementType $type): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $this->requirementFor($item, [
            'requirement_type' => $type,
            'target_duration_seconds' => null,
        ]);

        $result = $this->service()->evaluateVisualPlan($plan);

        $this->assertNotContains('MISSING_TARGET_DURATION', $this->codes($result['warnings']));
    }

    public static function nonSearchableRequirementTypes(): array
    {
        return [
            'graphic' => [AssetRequirementType::Graphic],
            'screen recording' => [AssetRequirementType::ScreenRecording],
            'other' => [AssetRequirementType::Other],
        ];
    }

    #[DataProvider('nonSearchableRequirementTypes')]
    #[Test]
    public function a_non_searchable_requirement_needs_no_search_query(AssetRequirementType $type): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $this->requirementFor($item, [
            'requirement_type' => $type,
            'search_query' => null,
        ]);

        $result = $this->service()->evaluateVisualPlan($plan);

        $this->assertNotContains('MISSING_ASSET_SEARCH_QUERY', $this->codes($result['warnings']));
    }

    #[Test]
    public function warnings_never_make_the_plan_not_ready(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planThatIsReady($project);
        $requirement = $plan->items()->first()->assetRequirements()->first();
        $requirement->update([
            'status' => AssetRequirementStatus::Searching,
            'search_query' => null,
            'target_duration_seconds' => null,
        ]);

        $result = $this->service()->evaluateVisualPlan($plan->fresh());

        $this->assertTrue($result['ready']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertSame([], $result['blockers']);
    }

    #[Test]
    public function an_item_with_a_blank_narration_or_prompt_lowers_the_completeness_score(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);
        $good = $this->itemFor($plan);
        $this->requirementFor($good);
        $bad = $this->itemFor($plan, ['narration_text' => '   ']);
        $this->requirementFor($bad);

        $result = $this->service()->evaluateVisualPlan($plan);

        $this->assertTrue($result['ready']);
        $this->assertSame(85, $result['score']);
    }

    #[Test]
    public function the_score_is_informational_and_never_decides_readiness(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planThatIsReady($project, ['status' => VisualPlanStatus::Approved]);
        $plan->items()->first()->assetRequirements()->first()->update(['search_query' => null]);

        $result = $this->service()->evaluateVisualPlan($plan->fresh());

        $this->assertTrue($result['ready']);
        $this->assertLessThan(100, $result['score']);
    }

    #[Test]
    public function an_item_with_nothing_specified_still_blocks_on_requirements_not_completeness(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan, [
            'narration_text' => '',
            'visual_prompt' => '',
        ]);
        $this->requirementFor($item);

        $result = $this->service()->evaluateVisualPlan($plan);

        $this->assertTrue($result['ready']);
        $this->assertSame(70, $result['score']);
    }

    #[Test]
    public function evaluating_a_plan_does_not_mutate_any_data(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planThatIsReady($project);
        $requirement = $plan->items()->first()->assetRequirements()->first();

        $before = [
            'plan' => $plan->getRawOriginal(),
            'item' => $plan->items()->first()->getRawOriginal(),
            'requirement' => $requirement->getRawOriginal(),
        ];

        $this->service()->evaluateVisualPlan($plan);

        $this->assertSame($before['plan'], $plan->fresh()->getRawOriginal());
        $this->assertSame($before['item'], $plan->items()->first()->getRawOriginal());
        $this->assertSame($before['requirement'], $requirement->fresh()->getRawOriginal());
    }

    #[Test]
    public function evaluating_a_plan_does_not_change_any_requirement_status(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planThatIsReady($project);
        $requirement = $plan->items()->first()->assetRequirements()->first();
        $requirement->update(['status' => AssetRequirementStatus::Searching]);

        $this->service()->evaluateVisualPlan($plan->fresh());

        $this->assertSame(
            AssetRequirementStatus::Searching,
            $requirement->fresh()->status
        );
    }

    #[Test]
    public function evaluating_a_plan_makes_no_outbound_http_request(): void
    {
        Http::preventStrayRequests();

        $project = ContentProject::factory()->create();
        $plan = $this->planThatIsReady($project);

        $this->service()->evaluateVisualPlan($plan);

        $this->assertTrue(true, 'No outbound request was attempted.');
    }

    #[Test]
    public function evaluating_a_plan_creates_no_new_rows(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planThatIsReady($project);

        $counts = [
            'items' => $plan->items()->count(),
            'requirements' => AssetRequirement::where('visual_plan_item_id', $plan->items()->first()->id)->count(),
            'plans' => VisualPlan::count(),
        ];

        $this->service()->evaluateVisualPlan($plan);

        $this->assertSame($counts['items'], $plan->items()->count());
        $this->assertSame($counts['requirements'], AssetRequirement::where('visual_plan_item_id', $plan->items()->first()->id)->count());
        $this->assertSame($counts['plans'], VisualPlan::count());
    }

    #[Test]
    public function evaluating_a_plan_does_not_transition_the_plan_status(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planThatIsReady($project, ['status' => VisualPlanStatus::Review]);

        $this->service()->evaluateVisualPlan($plan);

        $this->assertSame(VisualPlanStatus::Review, $plan->fresh()->status);
    }

    #[Test]
    public function a_corrupt_stored_plan_status_is_reported_instead_of_throwing(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planThatIsReady($project);

        DB::table('visual_plans')->where('id', $plan->id)->update(['status' => 'limbo']);

        $result = $this->service()->evaluateVisualPlan($plan->fresh());

        $this->assertTrue($result['ready']);
        $this->assertNotContains('ARCHIVED_VISUAL_PLAN', $this->codes($result['blockers']));

        $note = collect($result['info'])->firstWhere('code', 'PLAN_NOT_APPROVED');

        $this->assertNotNull($note);
        $this->assertStringContainsString('limbo', $note['message']);
    }

    #[Test]
    public function the_eager_load_avoids_a_query_per_item(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);
        $first = $this->itemFor($plan);
        $second = $this->itemFor($plan);
        $this->requirementFor($first);
        $this->requirementFor($second);

        $plan = VisualPlan::find($plan->id);
        DB::enableQueryLog();
        $this->service()->evaluateVisualPlan($plan);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(
            5,
            $queryCount,
            "Expected a constant small query count, got {$queryCount}."
        );
    }

    #[Test]
    public function the_gate_agrees_with_itself_on_repeated_evaluation(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $this->requirementFor($item, ['search_query' => null]);

        $first = $this->service()->evaluateVisualPlan($plan->fresh());
        $second = $this->service()->evaluateVisualPlan($plan->fresh());

        $this->assertSame($first, $second);
    }

    #[Test]
    public function a_fully_blocked_plan_reports_a_low_score(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $this->requirementFor($item, ['description' => '']);

        $result = $this->service()->evaluateVisualPlan($plan);

        $this->assertFalse($result['ready']);
        $this->assertSame(0, $result['summary']['valid_requirements']);
        $this->assertLessThan(100, $result['score']);
    }

    #[Test]
    public function blocker_and_warning_counts_match_the_reported_issues(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $this->requirementFor($item, [
            'status' => AssetRequirementStatus::Searching,
            'search_query' => null,
        ]);

        $result = $this->service()->evaluateVisualPlan($plan);

        $this->assertSame(count($result['blockers']), $result['summary']['blocker_count']);
        $this->assertSame(count($result['warnings']), $result['summary']['warning_count']);
    }

    #[Test]
    public function the_score_weights_the_documented_components(): void
    {
        $project = ContentProject::factory()->create();

        // Coverage and validity are perfect, but the one requirement is
        // searchable and temporal with no query and no duration: 30 + 30 + 20.
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $this->requirementFor($item, [
            'search_query' => null,
            'target_duration_seconds' => null,
        ]);

        $this->assertSame(80, $this->service()->evaluateVisualPlan($plan)['score']);

        // Dropping the requirement removes all 80 points of coverage,
        // validity, search and duration credit.
        $bare = $this->planFor($project, 2);
        $this->itemFor($bare);

        $this->assertSame(30, $this->service()->evaluateVisualPlan($bare)['score']);
    }

    #[Test]
    public function a_plan_item_relationship_actually_loads_requirements(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $this->requirementFor($item);

        $loaded = VisualPlan::with('items.assetRequirements')->find($plan->id);

        $this->assertTrue($loaded->items->first()->relationLoaded('assetRequirements'));
        $this->assertCount(1, $loaded->items->first()->assetRequirements);
    }

    #[Test]
    public function a_dangling_script_version_is_prevented_by_the_database(): void
    {
        $project = ContentProject::factory()->create();
        $plan = $this->planFor($project);

        // A visual plan may only point at a real script version, so the
        // cross-project case is the only referential gap the gate must catch.
        $this->expectException(QueryException::class);

        DB::table('visual_plans')->where('id', $plan->id)->update(['script_version_id' => 999999]);
    }
}
