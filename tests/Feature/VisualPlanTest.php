<?php

namespace Tests\Feature;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Enums\ResearchStatus;
use App\Enums\ScriptStatus;
use App\Enums\VisualPlanItemType;
use App\Enums\VisualPlanSection;
use App\Enums\VisualPlanStatus;
use App\Exceptions\DuplicateVisualPlanException;
use App\Exceptions\DuplicateVisualPlanItemOrderException;
use App\Exceptions\InvalidVisualPlanStatusTransitionException;
use App\Exceptions\VisualPlanNotReadyException;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Script;
use App\Models\Source;
use App\Models\User;
use App\Models\VisualPlan;
use App\Models\VisualPlanItem;
use App\Services\Script\ScriptQualityService;
use App\Services\VisualPlanService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class VisualPlanTest extends TestCase
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

    #[Test]
    public function visual_plan_belongs_to_the_project(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->scriptFor($project);
        $plan = VisualPlan::factory()->create([
            'content_project_id' => $project->id,
            'script_version_id' => $script->currentVersion->id,
        ]);

        $this->assertSame($project->id, $plan->project->id);
        $this->assertSame($plan->id, $project->visualPlans()->first()->id);
    }

    #[Test]
    public function visual_plan_belongs_to_the_script_version(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->scriptFor($project);
        $plan = VisualPlan::factory()->create([
            'content_project_id' => $project->id,
            'script_version_id' => $script->currentVersion->id,
        ]);

        $this->assertSame($script->currentVersion->id, $plan->scriptVersion->id);
        $this->assertSame($plan->id, $script->currentVersion->visualPlan->id);
    }

    #[Test]
    public function script_version_of_a_plan_belongs_to_the_same_project(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);

        $plan = $this->service()->createVisualPlan($project, 1, []);

        $this->assertSame($project->id, $plan->scriptVersion->script->content_project_id);
        $this->assertSame($project->script()->first()->id, $plan->scriptVersion->script_id);
    }

    #[Test]
    public function visual_plan_item_belongs_to_the_plan(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->scriptFor($project);
        $plan = VisualPlan::factory()->create([
            'content_project_id' => $project->id,
            'script_version_id' => $script->currentVersion->id,
        ]);
        $item = VisualPlanItem::factory()->create([
            'visual_plan_id' => $plan->id,
            'order' => 1,
        ]);

        $this->assertSame($plan->id, $item->visualPlan->id);
        $this->assertSame($item->id, $plan->items()->first()->id);
    }

    #[Test]
    public function deleting_the_project_cascades_to_plans_and_items(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->reviewScript($project);
        $plan = $this->service()->createVisualPlan($project, 1, []);
        $this->service()->createItem($project, 1, [
            'section' => VisualPlanSection::Hook->value,
            'narration_text' => 'Narration.',
            'visual_type' => VisualPlanItemType::Other->value,
            'visual_prompt' => 'Prompt.',
            'duration_seconds' => 5,
        ]);

        $this->assertSame(1, VisualPlan::count());
        $this->assertSame(1, VisualPlanItem::count());

        $project->delete();

        $this->assertSame(0, VisualPlan::count());
        $this->assertSame(0, VisualPlanItem::count());
        $this->assertSame(0, Script::count());
    }

    #[Test]
    public function deleting_the_script_version_cascades_to_the_plan(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->reviewScript($project);
        $this->service()->createVisualPlan($project, 1, []);

        $script->versions()->delete();

        $this->assertSame(0, VisualPlan::count());
    }

    #[Test]
    public function create_visual_plan_creates_an_empty_draft_for_the_exact_version(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->reviewScript($project);

        $plan = $this->service()->createVisualPlan($project, 1, ['title' => 'Storyboard']);

        $this->assertTrue($plan->status === VisualPlanStatus::Draft);
        $this->assertSame($script->currentVersion->id, $plan->script_version_id);
        $this->assertSame('Storyboard', $plan->title);
        $this->assertSame(0, $plan->items()->count());
    }

    #[Test]
    public function duplicate_visual_plan_for_the_same_version_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $this->service()->createVisualPlan($project, 1, []);

        $this->expectException(DuplicateVisualPlanException::class);

        $this->service()->createVisualPlan($project, 1, []);
    }

    #[Test]
    public function visual_plan_can_be_created_for_a_historical_version(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $this->scriptFor($project, 2, ['hook' => 'Second version hook.']);

        $plan = $this->service()->createVisualPlan($project, 2, []);

        $this->assertSame(2, $plan->scriptVersion->version);
        $this->assertSame('Second version hook.', $plan->scriptVersion->hook);
    }

    #[Test]
    public function create_visual_plan_rejects_a_non_ready_script(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->scriptFor($project);

        $this->expectException(VisualPlanNotReadyException::class);

        $this->service()->createVisualPlan($project, 1, []);
    }

    #[Test]
    public function create_visual_plan_never_mutates_script_research_or_quality_state(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->reviewScript($project);
        $report = $project->researchReport;
        $quality = app(ScriptQualityService::class);
        $before = $quality->evaluateScript($script->fresh());

        $this->service()->createVisualPlan($project, 1, []);

        $this->assertTrue($script->fresh()->status === ScriptStatus::Review);
        $this->assertTrue($report->fresh()->status === ResearchStatus::Completed);
        $this->assertSame(2, $report->fresh()->claims()->count());

        $after = $quality->evaluateScript($script->fresh());
        $this->assertSame($before['ready'], $after['ready']);
        $this->assertSame($before['score'], $after['score']);
        $this->assertSame('Why do cats purr?', $script->fresh()->currentVersion->hook);
    }

    #[Test]
    public function resolve_visual_plan_for_a_missing_script_version_throws_not_found(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->scriptFor($project);

        $this->expectException(ModelNotFoundException::class);

        $this->service()->getPlan($project, 99);
    }

    #[Test]
    public function resolve_visual_plan_does_not_leak_versions_from_another_script(): void
    {
        $user = User::factory()->create();
        $own = $this->readyProject($user);
        $other = $this->readyProject($user);
        $this->scriptFor($own);
        $this->scriptFor($other);
        $this->scriptFor($other, 2);

        $this->expectException(ModelNotFoundException::class);

        $this->service()->getPlan($own, 2);
    }

    #[Test]
    public function get_requested_missing_plan_returns_null(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->scriptFor($project);

        $this->assertNull($this->service()->getPlan($project, 1));
    }

    #[Test]
    public function create_item_appends_with_the_next_order_by_default(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $this->service()->createVisualPlan($project, 1, []);

        $first = $this->service()->createItem($project, 1, [
            'section' => VisualPlanSection::Hook->value,
            'narration_text' => 'First narration.',
            'visual_type' => VisualPlanItemType::Text->value,
            'visual_prompt' => 'A prompt.',
            'duration_seconds' => 4,
        ]);
        $second = $this->service()->createItem($project, 1, [
            'section' => VisualPlanSection::Body->value,
            'narration_text' => 'Second narration.',
            'visual_type' => VisualPlanItemType::Other->value,
            'visual_prompt' => 'A prompt.',
            'duration_seconds' => 6,
        ]);

        $this->assertSame(1, $first->order);
        $this->assertSame(2, $second->order);
    }

    #[Test]
    public function create_item_respects_an_explicit_order(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $this->service()->createVisualPlan($project, 1, []);

        $item = $this->service()->createItem($project, 1, [
            'order' => 5,
            'section' => VisualPlanSection::Body->value,
            'narration_text' => 'Narration.',
            'visual_type' => VisualPlanItemType::Photo->value,
            'visual_prompt' => 'A prompt.',
            'duration_seconds' => 3,
        ]);

        $this->assertSame(5, $item->order);
    }

    #[Test]
    public function create_item_rejects_a_duplicate_order(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $this->service()->createVisualPlan($project, 1, []);
        $this->service()->createItem($project, 1, [
            'section' => VisualPlanSection::Hook->value,
            'narration_text' => 'Narration.',
            'visual_type' => VisualPlanItemType::Other->value,
            'visual_prompt' => 'A prompt.',
            'duration_seconds' => 3,
        ]);

        $this->expectException(DuplicateVisualPlanItemOrderException::class);

        $this->service()->createItem($project, 1, [
            'order' => 1,
            'section' => VisualPlanSection::Body->value,
            'narration_text' => 'Narration.',
            'visual_type' => VisualPlanItemType::Other->value,
            'visual_prompt' => 'A prompt.',
            'duration_seconds' => 3,
        ]);
    }

    #[Test]
    public function created_items_are_listed_ordered_by_their_order_field(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $this->service()->createVisualPlan($project, 1, []);
        $this->service()->createItem($project, 1, [
            'order' => 3,
            'section' => VisualPlanSection::Closing->value,
            'narration_text' => 'Third.',
            'visual_type' => VisualPlanItemType::Other->value,
            'visual_prompt' => 'A prompt.',
            'duration_seconds' => 2,
        ]);
        $this->service()->createItem($project, 1, [
            'order' => 1,
            'section' => VisualPlanSection::Hook->value,
            'narration_text' => 'First.',
            'visual_type' => VisualPlanItemType::Other->value,
            'visual_prompt' => 'A prompt.',
            'duration_seconds' => 2,
        ]);
        $this->service()->createItem($project, 1, [
            'order' => 2,
            'section' => VisualPlanSection::Body->value,
            'narration_text' => 'Second.',
            'visual_type' => VisualPlanItemType::Other->value,
            'visual_prompt' => 'A prompt.',
            'duration_seconds' => 2,
        ]);

        $items = $this->service()->listItems($project, 1);

        $this->assertSame([1, 2, 3], $items->pluck('order')->all());
        $this->assertSame(['First.', 'Second.', 'Third.'], $items->pluck('narration_text')->all());
    }

    #[Test]
    public function update_item_replaces_only_the_provided_fields(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $this->service()->createVisualPlan($project, 1, []);
        $item = $this->service()->createItem($project, 1, [
            'section' => VisualPlanSection::Hook->value,
            'narration_text' => 'Original narration.',
            'visual_type' => VisualPlanItemType::Text->value,
            'visual_prompt' => 'Original prompt.',
            'duration_seconds' => 4,
            'notes' => 'Original notes.',
        ]);

        $updated = $this->service()->updateItem($project, 1, $item->id, [
            'visual_prompt' => 'Updated prompt.',
            'notes' => null,
        ]);

        $this->assertSame('Original narration.', $updated->narration_text);
        $this->assertSame('Updated prompt.', $updated->visual_prompt);
        $this->assertNull($updated->notes);
        $this->assertSame(4, $updated->duration_seconds);
    }

    #[Test]
    public function update_item_rejects_an_order_used_by_another_item(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $this->service()->createVisualPlan($project, 1, []);
        $this->service()->createItem($project, 1, [
            'section' => VisualPlanSection::Hook->value,
            'narration_text' => 'First.',
            'visual_type' => VisualPlanItemType::Other->value,
            'visual_prompt' => 'A prompt.',
            'duration_seconds' => 2,
        ]);
        $second = $this->service()->createItem($project, 1, [
            'section' => VisualPlanSection::Body->value,
            'narration_text' => 'Second.',
            'visual_type' => VisualPlanItemType::Other->value,
            'visual_prompt' => 'A prompt.',
            'duration_seconds' => 2,
        ]);

        $this->expectException(DuplicateVisualPlanItemOrderException::class);

        $this->service()->updateItem($project, 1, $second->id, ['order' => 1]);
    }

    #[Test]
    public function delete_item_removes_only_the_target_item(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $this->service()->createVisualPlan($project, 1, []);
        $first = $this->service()->createItem($project, 1, [
            'section' => VisualPlanSection::Hook->value,
            'narration_text' => 'First.',
            'visual_type' => VisualPlanItemType::Other->value,
            'visual_prompt' => 'A prompt.',
            'duration_seconds' => 2,
        ]);
        $this->service()->createItem($project, 1, [
            'section' => VisualPlanSection::Body->value,
            'narration_text' => 'Second.',
            'visual_type' => VisualPlanItemType::Other->value,
            'visual_prompt' => 'A prompt.',
            'duration_seconds' => 2,
        ]);

        $this->service()->deleteItem($project, 1, $first->id);

        $this->assertSame(1, $this->service()->listItems($project, 1)->count());
        $this->assertSame(VisualPlanSection::Body->value, $this->service()->listItems($project, 1)->first()->section->value);
    }

    #[Test]
    public function an_item_from_another_project_plan_cannot_be_manipulated(): void
    {
        $user = User::factory()->create();
        $own = $this->readyProject($user);
        $other = $this->readyProject($user);
        $this->reviewScript($own);
        $this->reviewScript($other);
        $this->service()->createVisualPlan($own, 1, []);
        $this->service()->createVisualPlan($other, 1, []);
        $otherItem = $this->service()->createItem($other, 1, [
            'section' => VisualPlanSection::Hook->value,
            'narration_text' => 'Other narration.',
            'visual_type' => VisualPlanItemType::Other->value,
            'visual_prompt' => 'A prompt.',
            'duration_seconds' => 2,
        ]);

        $this->expectException(ModelNotFoundException::class);

        $this->service()->deleteItem($own, 1, $otherItem->id);
    }

    #[Test]
    public function status_transition_works_through_the_workflow(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $plan = $this->service()->createVisualPlan($project, 1, []);

        $review = $this->service()->transitionStatus($plan, VisualPlanStatus::Review);
        $this->assertTrue($review->status === VisualPlanStatus::Review);

        $approved = $this->service()->transitionStatus($review, VisualPlanStatus::Approved);
        $this->assertTrue($approved->status === VisualPlanStatus::Approved);

        $archived = $this->service()->transitionStatus($approved, VisualPlanStatus::Archived);
        $this->assertTrue($archived->status === VisualPlanStatus::Archived);
    }

    #[Test]
    public function invalid_status_transition_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $plan = $this->service()->createVisualPlan($project, 1, []);

        $this->expectException(InvalidVisualPlanStatusTransitionException::class);

        $this->service()->transitionStatus($plan, VisualPlanStatus::Approved);
    }

    #[Test]
    public function same_status_transition_is_rejected(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $plan = $this->service()->createVisualPlan($project, 1, []);

        $this->expectException(InvalidVisualPlanStatusTransitionException::class);

        $this->service()->transitionStatus($plan, VisualPlanStatus::Draft);
    }

    #[Test]
    public function create_from_script_seeds_a_hook_body_and_closing_item_in_order(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);

        $plan = $this->service()->createVisualPlanFromScript($project, 1);

        $this->assertSame(3, $plan->items()->count());
        $this->assertSame([1, 2, 3], $plan->items()->orderBy('order')->pluck('order')->all());

        $items = $plan->items()->orderBy('order')->get();
        $this->assertSame(VisualPlanSection::Hook->value, $items[0]->section->value);
        $this->assertSame('Why do cats purr?', $items[0]->narration_text);
        $this->assertSame(VisualPlanSection::Body->value, $items[1]->section->value);
        $this->assertSame("Cats purr to communicate with humans, and purring helps cats soothe themselves when stressed.\n", $items[1]->narration_text);
        $this->assertSame(VisualPlanSection::Closing->value, $items[2]->section->value);
        $this->assertSame('And now you know.', $items[2]->narration_text);
        $this->assertSame(VisualPlanItemType::Other->value, $items[0]->visual_type->value);
        $this->assertSame(VisualPlanService::PLACEHOLDER_VISUAL_PROMPT, $items[0]->visual_prompt);
        $this->assertSame(VisualPlanService::DEFAULT_ITEM_DURATION_SECONDS, $items[0]->duration_seconds);
    }

    #[Test]
    public function create_from_script_leaves_the_source_version_unchanged(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->reviewScript($project);

        $this->service()->createVisualPlanFromScript($project, 1);

        $this->assertSame('Why do cats purr?', $script->fresh()->currentVersion->hook);
        $this->assertSame("Cats purr to communicate with humans, and purring helps cats soothe themselves when stressed.\n", $script->fresh()->currentVersion->body);
        $this->assertSame('And now you know.', $script->fresh()->currentVersion->closing);
        $this->assertSame(1, $script->fresh()->versions()->count());
    }

    #[Test]
    public function create_from_script_never_creates_research_mappings(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);

        $this->service()->createVisualPlanFromScript($project, 1);

        $this->assertDatabaseCount('script_version_research_claim', 0);
        $this->assertSame(0, $project->script->currentVersion->researchClaims()->count());
    }

    #[Test]
    public function reorder_updates_every_item_order_deterministically(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $plan = $this->service()->createVisualPlan($project, 1, []);
        $first = $this->service()->createItem($project, 1, [
            'section' => VisualPlanSection::Hook->value,
            'narration_text' => 'First.',
            'visual_type' => VisualPlanItemType::Other->value,
            'visual_prompt' => 'A prompt.',
            'duration_seconds' => 2,
        ]);
        $second = $this->service()->createItem($project, 1, [
            'section' => VisualPlanSection::Body->value,
            'narration_text' => 'Second.',
            'visual_type' => VisualPlanItemType::Other->value,
            'visual_prompt' => 'A prompt.',
            'duration_seconds' => 2,
        ]);
        $third = $this->service()->createItem($project, 1, [
            'section' => VisualPlanSection::Closing->value,
            'narration_text' => 'Third.',
            'visual_type' => VisualPlanItemType::Other->value,
            'visual_prompt' => 'A prompt.',
            'duration_seconds' => 2,
        ]);

        $this->service()->reorderItems($project, 1, [
            ['id' => $first->id, 'order' => 3],
            ['id' => $second->id, 'order' => 1],
            ['id' => $third->id, 'order' => 2],
        ]);

        $items = $plan->items()->orderBy('order')->get();

        $this->assertSame([1, 2, 3], $items->pluck('order')->all());
        $this->assertSame([$second->id, $third->id, $first->id], $items->pluck('id')->all());
    }

    #[Test]
    public function reorder_rejects_duplicate_orders(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $this->service()->createVisualPlan($project, 1, []);
        $first = $this->service()->createItem($project, 1, [
            'section' => VisualPlanSection::Hook->value,
            'narration_text' => 'First.',
            'visual_type' => VisualPlanItemType::Other->value,
            'visual_prompt' => 'A prompt.',
            'duration_seconds' => 2,
        ]);
        $second = $this->service()->createItem($project, 1, [
            'section' => VisualPlanSection::Body->value,
            'narration_text' => 'Second.',
            'visual_type' => VisualPlanItemType::Other->value,
            'visual_prompt' => 'A prompt.',
            'duration_seconds' => 2,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->reorderItems($project, 1, [
            ['id' => $first->id, 'order' => 1],
            ['id' => $second->id, 'order' => 1],
        ]);
    }

    #[Test]
    public function reorder_rejects_an_item_from_another_plan(): void
    {
        $user = User::factory()->create();
        $own = $this->readyProject($user);
        $other = $this->readyProject($user);
        $this->reviewScript($own);
        $this->reviewScript($other);
        $this->service()->createVisualPlan($own, 1, []);
        $this->service()->createVisualPlan($other, 1, []);
        $ownItem = $this->service()->createItem($own, 1, [
            'section' => VisualPlanSection::Hook->value,
            'narration_text' => 'First.',
            'visual_type' => VisualPlanItemType::Other->value,
            'visual_prompt' => 'A prompt.',
            'duration_seconds' => 2,
        ]);
        $foreignItem = $this->service()->createItem($other, 1, [
            'section' => VisualPlanSection::Body->value,
            'narration_text' => 'Foreign.',
            'visual_type' => VisualPlanItemType::Other->value,
            'visual_prompt' => 'A prompt.',
            'duration_seconds' => 2,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->reorderItems($own, 1, [
            ['id' => $ownItem->id, 'order' => 1],
            ['id' => $foreignItem->id, 'order' => 2],
        ]);
    }

    #[Test]
    public function reorder_rolls_back_every_change_when_an_update_fails(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);
        $plan = $this->service()->createVisualPlan($project, 1, []);
        $first = $this->service()->createItem($project, 1, [
            'section' => VisualPlanSection::Hook->value,
            'narration_text' => 'First.',
            'visual_type' => VisualPlanItemType::Other->value,
            'visual_prompt' => 'A prompt.',
            'duration_seconds' => 2,
        ]);
        $second = $this->service()->createItem($project, 1, [
            'section' => VisualPlanSection::Body->value,
            'narration_text' => 'Second.',
            'visual_type' => VisualPlanItemType::Other->value,
            'visual_prompt' => 'A prompt.',
            'duration_seconds' => 2,
        ]);
        $third = $this->service()->createItem($project, 1, [
            'section' => VisualPlanSection::Closing->value,
            'narration_text' => 'Third.',
            'visual_type' => VisualPlanItemType::Other->value,
            'visual_prompt' => 'A prompt.',
            'duration_seconds' => 2,
        ]);

        VisualPlanItem::updating(function ($item) {
            if ($item->order > 900_000) {
                throw new RuntimeException('boom');
            }
        });

        try {
            $this->expectException(RuntimeException::class);
            $this->service()->reorderItems($project, 1, [
                ['id' => $first->id, 'order' => 3],
                ['id' => $second->id, 'order' => 1],
                ['id' => $third->id, 'order' => 2],
            ]);
        } finally {
            VisualPlanItem::flushEventListeners();
        }

        $items = $plan->items()->orderBy('order')->get();

        $this->assertSame([1, 2, 3], $items->pluck('order')->all());
    }
}
