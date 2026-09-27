<?php

namespace Tests\Feature;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Enums\ScriptStatus;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Script;
use App\Models\ScriptVersion;
use App\Models\Source;
use App\Models\User;
use App\Services\Script\ScriptQualityService;
use App\Services\Script\ScriptResearchTraceabilityService;
use App\Services\ScriptService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ScriptRevisionTest extends TestCase
{
    use RefreshDatabase;

    private function readyProject(User $user): ContentProject
    {
        $project = ContentProject::factory()->for($user, 'creator')->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claims = ResearchClaim::factory()->count(2)->create([
            'research_report_id' => $report->id,
            'status' => ResearchClaimStatus::Supported,
            'importance' => ResearchClaimImportance::High,
        ]);
        foreach ($claims as $claim) {
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
            'body' => "Cats purr for many reasons, including contentment and self-soothing.\n",
            'closing' => 'And now you know.',
            'duration_seconds' => 45,
            'notes' => 'Record with soft background music.',
        ], $state));

        $script->update(['current_version_id' => $versionModel->id]);

        $script->load('currentVersion');

        return $script;
    }

    private function service(): ScriptService
    {
        return app(ScriptService::class);
    }

    #[Test]
    public function revision_creates_a_new_immutable_version_and_returns_a_fresh_script(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->scriptFor($project);

        $updated = $this->service()->createRevision($project, 1, ['hook' => 'A sharper hook.']);

        $this->assertInstanceOf(Script::class, $updated);
        $this->assertSame(2, $updated->currentVersion->version);
        $this->assertSame('A sharper hook.', $updated->currentVersion->hook);
    }

    #[Test]
    public function revision_increments_the_version_number(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->scriptFor($project);
        $this->scriptFor($project, 2);

        $updated = $this->service()->createRevision($project, 1, ['hook' => 'Tighter.']);

        $this->assertSame(3, $updated->currentVersion->version);
        $this->assertSame(3, $updated->versions()->count());
    }

    #[Test]
    public function revision_leaves_the_source_version_unchanged(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $original = $this->scriptFor($project);

        $this->service()->createRevision($project, 1, ['hook' => 'A sharper hook.']);

        $source = $original->versions()->where('version', 1)->first();

        $this->assertSame('Why do cats purr?', $source->hook);
        $this->assertSame("Cats purr for many reasons, including contentment and self-soothing.\n", $source->body);
        $this->assertSame('And now you know.', $source->closing);
    }

    #[Test]
    public function revision_with_only_a_hook_inherits_body_and_closing_from_the_source(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $original = $this->scriptFor($project);

        $updated = $this->service()->createRevision($project, 1, ['hook' => 'A sharper hook.']);

        $this->assertSame($original->currentVersion->body, $updated->currentVersion->body);
        $this->assertSame($original->currentVersion->closing, $updated->currentVersion->closing);
    }

    #[Test]
    public function revision_with_only_a_body_inherits_hook_and_closing_from_the_source(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $original = $this->scriptFor($project);

        $updated = $this->service()->createRevision($project, 1, ['body' => 'A rewritten body.']);

        $this->assertSame($original->currentVersion->hook, $updated->currentVersion->hook);
        $this->assertSame($original->currentVersion->closing, $updated->currentVersion->closing);
        $this->assertSame('A rewritten body.', $updated->currentVersion->body);
    }

    #[Test]
    public function revision_with_only_a_closing_inherits_hook_and_body_from_the_source(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $original = $this->scriptFor($project);

        $updated = $this->service()->createRevision($project, 1, ['closing' => 'A new sign-off.']);

        $this->assertSame($original->currentVersion->hook, $updated->currentVersion->hook);
        $this->assertSame($original->currentVersion->body, $updated->currentVersion->body);
        $this->assertSame('A new sign-off.', $updated->currentVersion->closing);
    }

    #[Test]
    public function revision_replaces_multiple_editable_fields_at_once(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $original = $this->scriptFor($project);

        $updated = $this->service()->createRevision($project, 1, [
            'hook' => 'Fresh hook.',
            'closing' => 'Fresh closing.',
        ]);

        $this->assertSame('Fresh hook.', $updated->currentVersion->hook);
        $this->assertSame('Fresh closing.', $updated->currentVersion->closing);
        $this->assertSame($original->currentVersion->body, $updated->currentVersion->body);
    }

    #[Test]
    public function revision_inherits_title_duration_and_notes_from_the_source(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->scriptFor($project);

        $updated = $this->service()->createRevision($project, 1, ['hook' => 'Fresh hook.']);

        $this->assertSame('How Cats Purr', $updated->currentVersion->title);
        $this->assertSame(45, $updated->currentVersion->duration_seconds);
        $this->assertSame('Record with soft background music.', $updated->currentVersion->notes);
    }

    #[Test]
    public function revision_never_stores_non_editable_fields_even_if_provided(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->scriptFor($project);

        $updated = $this->service()->createRevision($project, 1, [
            'hook' => 'Fresh hook.',
            'title' => 'Hacked title.',
            'duration_seconds' => 9999,
        ]);

        $this->assertSame('Fresh hook.', $updated->currentVersion->hook);
        $this->assertSame('How Cats Purr', $updated->currentVersion->title);
        $this->assertSame(45, $updated->currentVersion->duration_seconds);
    }

    #[Test]
    public function revision_makes_the_new_version_current_and_forces_the_script_back_to_draft(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->scriptFor($project);
        $script->update(['status' => ScriptStatus::Approved]);

        $updated = $this->service()->createRevision($project, 1, ['hook' => 'Fresh hook.']);

        $this->assertTrue($updated->status === ScriptStatus::Draft);
        $this->assertSame($updated->currentVersion->id, $updated->current_version_id);
        $this->assertSame(2, $updated->currentVersion->version);
    }

    #[Test]
    public function revision_can_use_a_historical_version_as_its_source(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->scriptFor($project);
        $this->scriptFor($project, 2, ['hook' => 'Second version hook.']);

        $updated = $this->service()->createRevision($project, 1, ['hook' => 'Rebuilt from v1.']);

        $this->assertSame(3, $updated->currentVersion->version);
        $this->assertSame('Rebuilt from v1.', $updated->currentVersion->hook);
        $this->assertSame('Second version hook.', $updated->versions()->where('version', 2)->first()->hook);
    }

    #[Test]
    public function revision_still_works_from_an_archived_script_and_forces_draft(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->scriptFor($project);
        $script->update(['status' => ScriptStatus::Archived]);

        $updated = $this->service()->createRevision($project, 1, ['hook' => 'Revived from archive.']);

        $this->assertTrue($updated->status === ScriptStatus::Draft);
        $this->assertSame('Revived from archive.', $updated->currentVersion->hook);
    }

    #[Test]
    public function revision_with_an_unknown_source_version_throws_a_not_found_exception(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->scriptFor($project);

        $this->expectException(ModelNotFoundException::class);

        try {
            $this->service()->createRevision($project, 99, ['hook' => 'Nope.']);
        } finally {
            $this->assertSame(1, $project->script()->count());
        }
    }

    #[Test]
    public function revision_resolves_versions_only_within_the_target_script(): void
    {
        $user = User::factory()->create();
        $own = $this->readyProject($user);
        $other = $this->readyProject($user);
        $ownScript = $this->scriptFor($own);
        $this->scriptFor($other);
        // The other project has a v1 and v2; the target script only has v1.
        $this->scriptFor($other, 2);

        $this->expectException(ModelNotFoundException::class);

        try {
            $this->service()->createRevision($own, 2, ['hook' => 'Cross-project.']);
        } finally {
            $this->assertSame('Why do cats purr?', $ownScript->currentVersion->hook);
            $this->assertSame(1, $own->script()->count());
        }
    }

    #[Test]
    public function revision_is_transaction_safe_and_rolls_back_on_failure(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->scriptFor($project);
        $script->update(['status' => ScriptStatus::Review]);

        ScriptVersion::creating(function () {
            throw new RuntimeException('boom');
        });

        try {
            $this->expectException(RuntimeException::class);
            $this->service()->createRevision($project, 1, ['hook' => 'Should fail.']);
        } finally {
            ScriptVersion::flushEventListeners();
        }

        $this->assertSame(1, $script->versions()->count());
        $this->assertSame($script->currentVersion->id, $script->current_version_id);
        $this->assertTrue($script->fresh()->status === ScriptStatus::Review);
        $this->assertSame('Why do cats purr?', $script->currentVersion->hook);
    }

    #[Test]
    public function revision_never_copies_research_claim_mappings_to_the_new_version(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->scriptFor($project);
        app(ScriptResearchTraceabilityService::class)->attachClaim($project, $script->currentVersion, $project->researchReport->claims->first());

        $updated = $this->service()->createRevision($project, 1, ['hook' => 'Fresh hook.']);

        $this->assertSame(0, $updated->currentVersion->researchClaims()->count());
    }

    #[Test]
    public function original_version_keeps_its_own_mappings_after_a_revision(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->scriptFor($project);
        $firstClaim = $project->researchReport->claims->first();
        app(ScriptResearchTraceabilityService::class)->attachClaim($project, $script->currentVersion, $firstClaim);

        $this->service()->createRevision($project, 1, ['hook' => 'Fresh hook.']);

        $source = $script->versions()->where('version', 1)->first();

        $this->assertSame(1, $source->researchClaims()->count());
        $this->assertSame($firstClaim->id, $source->researchClaims()->first()->id);
    }

    #[Test]
    public function revised_version_can_be_mapped_and_detaching_it_leaves_the_original_alone(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->scriptFor($project);
        $firstClaim = $project->researchReport->claims->first();
        $secondClaim = $project->researchReport->claims->get(1);
        $traceability = app(ScriptResearchTraceabilityService::class);
        $traceability->attachClaim($project, $script->currentVersion, $firstClaim);

        $updated = $this->service()->createRevision($project, 1, ['hook' => 'Fresh hook.']);
        $traceability->attachClaim($project, $updated->currentVersion, $secondClaim);

        $this->assertSame(1, $updated->currentVersion->researchClaims()->count());

        $traceability->detachClaim($project, $updated->currentVersion, $secondClaim);

        $this->assertSame(0, $updated->currentVersion->researchClaims()->count());
        $this->assertSame(1, $script->versions()->where('version', 1)->first()->researchClaims()->count());
    }

    #[Test]
    public function traceability_summaries_remain_independent_between_original_and_revised_versions(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->scriptFor($project);
        $traceability = app(ScriptResearchTraceabilityService::class);
        $traceability->attachClaim($project, $script->currentVersion, $project->researchReport->claims->first());

        $originalSummary = $traceability->summary($script->currentVersion);
        $updated = $this->service()->createRevision($project, 1, ['hook' => 'Fresh hook.']);
        $revisedSummary = $traceability->summary($updated->currentVersion);

        $this->assertSame(1, $originalSummary['total_claims']);
        $this->assertTrue($originalSummary['traceability_complete']);
        $this->assertSame(0, $revisedSummary['total_claims']);
        $this->assertFalse($revisedSummary['traceability_complete']);
        $this->assertContains('No research claims are mapped to this script version yet.', $revisedSummary['warnings']);
    }

    #[Test]
    public function revision_quality_is_recalculated_against_the_new_version(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->scriptFor($project);
        $quality = app(ScriptQualityService::class);

        $sourceVersionId = $script->currentVersion->id;

        $updated = $this->service()->createRevision($project, 1, ['hook' => 'A sharper hook.']);
        $after = $quality->evaluateScript($updated);

        $currentVersionCheck = collect($after['checks'])->firstWhere('code', 'NO_CURRENT_VERSION');

        $this->assertSame($sourceVersionId + 1, $currentVersionCheck['details']['version_id']);
        $this->assertTrue($currentVersionCheck['passed']);
        $this->assertGreaterThan(0, $after['summary']['total_checks']);
    }

    #[Test]
    public function revision_is_read_only_and_never_mutates_research_or_original_versions(): void
    {
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->scriptFor($project);
        app(ScriptQualityService::class)->evaluateScript($script->fresh());
        $report = $project->researchReport;

        $this->assertSame(2, $report->claims()->count());

        $this->service()->createRevision($project, 1, ['hook' => 'A sharper hook.']);

        $this->assertSame(2, $report->fresh()->claims()->count());
        $this->assertSame(2, $script->versions()->count());
        $this->assertSame('Why do cats purr?', $script->versions()->where('version', 1)->first()->hook);
    }
}
