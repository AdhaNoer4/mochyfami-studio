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
use App\Services\Script\ScriptQualityService;
use App\Services\ScriptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScriptQualityTest extends TestCase
{
    use RefreshDatabase;

    private function qualityService(): ScriptQualityService
    {
        return app(ScriptQualityService::class);
    }

    private function scriptService(): ScriptService
    {
        return app(ScriptService::class);
    }

    /**
     * Create a project whose research is ready: one supported high claim
     * with evidence, whose text can be absent or present in the script.
     *
     * @return array{0: ContentProject, 1: ResearchReport}
     */
    private function projectWithReadyResearch(string $claim = 'Cats purr to communicate with humans'): array
    {
        $project = ContentProject::factory()->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'claim' => $claim,
            'status' => ResearchClaimStatus::Supported,
            'importance' => ResearchClaimImportance::High,
        ]);
        $claim->sources()->attach($source->id);

        return [$project, $report];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Why Cats Purr',
            'hook' => 'Why do cats purr?',
            'body' => "Cats purr to communicate with humans, and it soothes them.\n",
            'closing' => 'Now you know why cats purr.',
            'duration_seconds' => 45,
            'notes' => null,
        ], $overrides);
    }

    private function reviewReadyScript(ContentProject $project, array $overrides = []): Script
    {
        $this->scriptService()->createScript($project, $this->payload($overrides));

        return $this->scriptService()->transitionStatus($project, ScriptStatus::Review);
    }

    private function scriptWithVersion(
        ContentProject $project,
        array $versionOverrides = [],
        ScriptStatus $status = ScriptStatus::Review
    ): Script {
        $script = Script::factory()
            ->has(ScriptVersion::factory()->state(array_merge([
                'version' => 1,
                'hook' => 'Why do cats purr?',
                'body' => "Cats purr to communicate with humans.\n",
                'closing' => 'Now you know.',
            ], $versionOverrides)), 'versions')
            ->create(['content_project_id' => $project->id, 'status' => $status]);

        $script->update(['current_version_id' => $script->versions()->first()->id]);
        $script->load(['project', 'currentVersion']);

        return $script;
    }

    public function test_script_without_current_version_has_blocker(): void
    {
        [$project] = $this->projectWithReadyResearch();
        $script = Script::factory()->create(['content_project_id' => $project->id]);

        $result = $this->qualityService()->evaluateScript($script);

        $this->assertFalse($result['ready']);
        $codes = array_column($result['blockers'], 'code');
        $this->assertContains('NO_CURRENT_VERSION', $codes);
        $this->assertSame(0, $result['summary']['important_claims']);
    }

    public function test_empty_script_is_blocker(): void
    {
        [$project] = $this->projectWithReadyResearch();
        $script = $this->reviewReadyScript($project, ['hook' => '', 'body' => '', 'closing' => '']);

        $result = $this->qualityService()->evaluateScript($script);

        $this->assertFalse($result['ready']);
        $this->assertContains(
            'EMPTY_SCRIPT',
            array_column($result['blockers'], 'code')
        );
    }

    public function test_missing_research_report_is_blocker(): void
    {
        $project = ContentProject::factory()->create();
        $script = $this->scriptWithVersion($project);

        $result = $this->qualityService()->evaluateScript($script);

        $this->assertFalse($result['ready']);
        $this->assertContains(
            'RESEARCH_NOT_READY',
            array_column($result['blockers'], 'code')
        );
        $this->assertEmpty($result['claim_alignment']);
    }

    public function test_unready_research_is_blocker(): void
    {
        $project = ContentProject::factory()->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'claim' => 'Cats purr to communicate with humans',
            'status' => ResearchClaimStatus::Unverified,
            'importance' => ResearchClaimImportance::High,
        ]);
        $claim->sources()->attach($source->id);
        $script = $this->scriptWithVersion($project);

        $result = $this->qualityService()->evaluateScript($script);

        $this->assertFalse($result['ready']);
        $this->assertContains(
            'RESEARCH_NOT_READY',
            array_column($result['blockers'], 'code')
        );
        $this->assertContains(
            'IMPORTANT_CLAIM_NOT_RESEARCH_READY',
            array_column($result['blockers'], 'code')
        );
    }

    public function test_draft_status_is_blocker_for_ready(): void
    {
        [$project] = $this->projectWithReadyResearch();
        $script = $this->scriptService()->createScript($project, $this->payload());

        $result = $this->qualityService()->evaluateScript($script);

        $this->assertFalse($result['ready']);
        $this->assertContains(
            'SCRIPT_STATUS_NOT_REVIEWABLE',
            array_column($result['blockers'], 'code')
        );
        $this->assertSame(85, $result['score']);
    }

    public function test_review_status_with_aligned_claims_is_ready(): void
    {
        [$project] = $this->projectWithReadyResearch();
        $script = $this->reviewReadyScript($project);

        $result = $this->qualityService()->evaluateScript($script);

        $this->assertTrue($result['ready']);
        $this->assertSame(100, $result['score']);
        $this->assertSame(0, $result['summary']['blocker_count']);
        $this->assertSame(1, $result['summary']['important_claims']);
        $this->assertSame(1, $result['summary']['aligned_important_claims']);
        $this->assertSame('supported_by_text', $result['claim_alignment'][0]['match_state']);
    }

    public function test_approved_status_is_ready(): void
    {
        [$project] = $this->projectWithReadyResearch();
        $script = $this->reviewReadyScript($project);
        $script = $this->scriptService()->transitionStatus($project, ScriptStatus::Approved);

        $result = $this->qualityService()->evaluateScript($script);

        $this->assertTrue($result['ready']);
        $this->assertSame(100, $result['score']);
    }

    public function test_placeholder_content_is_a_warning_not_blocker(): void
    {
        [$project] = $this->projectWithReadyResearch();
        $script = $this->reviewReadyScript($project, [
            'body' => "Cats purr to communicate with humans. TODO: expand this part {{insert title}} lorem ipsum.\n",
        ]);

        $result = $this->qualityService()->evaluateScript($script);

        $this->assertTrue($result['ready']);
        $this->assertContains(
            'PLACEHOLDER_CONTENT',
            array_column($result['warnings'], 'code')
        );
        $this->assertSame(90, $result['score']);
    }

    public function test_placeholder_patterns_are_case_insensitive(): void
    {
        [$project] = $this->projectWithReadyResearch();
        $script = $this->reviewReadyScript($project, [
            'body' => "Cats purr to communicate with humans. todo: record later. <Placeholder> here.\n",
        ]);

        $result = $this->qualityService()->evaluateScript($script);

        $this->assertContains(
            'PLACEHOLDER_CONTENT',
            array_column($result['warnings'], 'code')
        );
    }

    public function test_missing_hook_is_a_warning(): void
    {
        [$project] = $this->projectWithReadyResearch();
        $script = $this->reviewReadyScript($project, ['hook' => '']);

        $result = $this->qualityService()->evaluateScript($script);

        $this->assertTrue($result['ready']);
        $this->assertContains('MISSING_HOOK', array_column($result['warnings'], 'code'));
        $this->assertSame(92, $result['score']);
    }

    public function test_missing_body_is_a_warning(): void
    {
        $project = ContentProject::factory()->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'claim' => 'Telepathic squirrels hold secret council meetings',
            'status' => ResearchClaimStatus::Supported,
            'importance' => ResearchClaimImportance::Medium,
        ]);
        $claim->sources()->attach($source->id);
        $script = $this->reviewReadyScript($project, ['body' => '']);

        $result = $this->qualityService()->evaluateScript($script);

        $this->assertTrue($result['ready']);
        $this->assertContains('MISSING_BODY', array_column($result['warnings'], 'code'));
        $this->assertSame(88, $result['score']);
    }

    public function test_missing_closing_is_a_warning(): void
    {
        [$project] = $this->projectWithReadyResearch();
        $script = $this->reviewReadyScript($project, ['closing' => '']);

        $result = $this->qualityService()->evaluateScript($script);

        $this->assertTrue($result['ready']);
        $this->assertContains('MISSING_CLOSING', array_column($result['warnings'], 'code'));
        $this->assertSame(95, $result['score']);
    }

    public function test_supported_high_claim_not_aligned_is_blocker(): void
    {
        [$project] = $this->projectWithReadyResearch();
        $script = $this->reviewReadyScript($project, [
            'body' => "Lorem ipsum dolor sit amet, consectetur adipiscing elit.\n",
        ]);

        $result = $this->qualityService()->evaluateScript($script);

        $this->assertFalse($result['ready']);
        $this->assertContains(
            'IMPORTANT_CLAIM_NOT_ALIGNED',
            array_column($result['blockers'], 'code')
        );
        $this->assertSame('not_detected', $result['claim_alignment'][0]['match_state']);
        $this->assertLessThan(50, $result['claim_alignment'][0]['match_score']);
    }

    public function test_non_important_claim_not_aligned_is_warning(): void
    {
        $project = ContentProject::factory()->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'claim' => 'Telepathic squirrels hold secret council meetings',
            'status' => ResearchClaimStatus::Supported,
            'importance' => ResearchClaimImportance::Medium,
        ]);
        $claim->sources()->attach($source->id);
        $script = $this->reviewReadyScript($project);

        $result = $this->qualityService()->evaluateScript($script);

        $this->assertTrue($result['ready']);
        $this->assertContains('CLAIM_NOT_ALIGNED', array_column($result['warnings'], 'code'));
        $this->assertSame(0, $result['summary']['important_claims']);
    }

    public function test_high_claim_with_insufficient_text_is_blocker(): void
    {
        [$project] = $this->projectWithReadyResearch('cukup');
        $script = $this->reviewReadyScript($project);

        $result = $this->qualityService()->evaluateScript($script);

        $this->assertFalse($result['ready']);
        $this->assertSame('insufficient_text', $result['claim_alignment'][0]['match_state']);
        $this->assertContains(
            'IMPORTANT_CLAIM_NOT_ALIGNED',
            array_column($result['blockers'], 'code')
        );
    }

    public function test_uncertain_high_claim_uses_safe_wording(): void
    {
        $project = ContentProject::factory()->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'claim' => 'Cats purr to communicate with humans',
            'status' => ResearchClaimStatus::Uncertain,
            'importance' => ResearchClaimImportance::High,
        ]);
        $claim->sources()->attach($source->id);
        $script = $this->scriptWithVersion($project);

        $result = $this->qualityService()->evaluateScript($script);

        $blocker = collect($result['blockers'])->firstWhere('code', 'IMPORTANT_CLAIM_NOT_RESEARCH_READY');
        $this->assertSame('An important research claim is not sufficiently verified.', $blocker['message']);
        $this->assertFalse($result['ready']);
    }

    public function test_contradicted_high_claim_is_blocker(): void
    {
        $project = ContentProject::factory()->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'claim' => 'Cats purr to communicate with humans',
            'status' => ResearchClaimStatus::Contradicted,
            'importance' => ResearchClaimImportance::High,
        ]);
        $claim->sources()->attach($source->id);
        $script = $this->scriptWithVersion($project);

        $result = $this->qualityService()->evaluateScript($script);

        $this->assertFalse($result['ready']);
        $this->assertContains(
            'IMPORTANT_CLAIM_NOT_RESEARCH_READY',
            array_column($result['blockers'], 'code')
        );
    }

    public function test_unverified_high_claim_is_blocker(): void
    {
        $project = ContentProject::factory()->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'claim' => 'Cats purr to communicate with humans',
            'status' => ResearchClaimStatus::Unverified,
            'importance' => ResearchClaimImportance::High,
        ]);
        $claim->sources()->attach($source->id);
        $script = $this->scriptWithVersion($project);

        $result = $this->qualityService()->evaluateScript($script);

        $this->assertFalse($result['ready']);
        $this->assertContains(
            'IMPORTANT_CLAIM_NOT_RESEARCH_READY',
            array_column($result['blockers'], 'code')
        );
    }

    public function test_score_combines_all_components(): void
    {
        [$project] = $this->projectWithReadyResearch();
        $script = $this->reviewReadyScript($project);
        $this->assertSame(100, $this->qualityService()->evaluateScript($script)['score']);

        [$draftProject] = $this->projectWithReadyResearch();
        $draft = $this->scriptService()->createScript($draftProject, $this->payload());
        $this->assertSame(85, $this->qualityService()->evaluateScript($draft)['score']);

        [$missingSectionsProject] = $this->projectWithReadyResearch();
        $missingHook = $this->reviewReadyScript($missingSectionsProject, ['closing' => '', 'hook' => '']);
        $this->assertSame(87, $this->qualityService()->evaluateScript($missingHook)['score']);

        [$longProject] = $this->projectWithReadyResearch();
        $long = $this->reviewReadyScript($longProject, ['duration_seconds' => 120]);
        $longResult = $this->qualityService()->evaluateScript($long);
        $this->assertSame(100, $longResult['score']);
        $this->assertContains('SCRIPT_TOO_LONG', array_column($longResult['warnings'], 'code'));
    }

    public function test_ready_is_false_when_any_blocker_exists_even_with_high_score(): void
    {
        [$project] = $this->projectWithReadyResearch();
        $draft = $this->scriptService()->createScript($project, $this->payload());

        $result = $this->qualityService()->evaluateScript($draft);

        $this->assertSame(85, $result['score']);
        $this->assertFalse($result['ready']);
    }

    public function test_iteration_is_deterministic(): void
    {
        [$project] = $this->projectWithReadyResearch();
        $script = $this->reviewReadyScript($project);

        $first = $this->qualityService()->evaluateScript($script);
        $second = $this->qualityService()->evaluateScript($script);

        $this->assertSame($first, $second);
        $this->assertSame($first['score'], $second['score']);
    }

    public function test_evaluation_does_not_mutate_database(): void
    {
        [$project] = $this->projectWithReadyResearch();
        $script = $this->reviewReadyScript($project);

        $countsBefore = [
            'scripts' => DB::table('scripts')->count(),
            'versions' => DB::table('script_versions')->count(),
            'reports' => DB::table('research_reports')->count(),
            'claims' => DB::table('research_claims')->count(),
            'sources' => DB::table('sources')->count(),
        ];
        $statusBefore = $script->status->value;
        $versionNumberBefore = $script->currentVersion->version;

        $this->qualityService()->evaluateScript($script);

        $this->assertSame($countsBefore['scripts'], DB::table('scripts')->count());
        $this->assertSame($countsBefore['versions'], DB::table('script_versions')->count());
        $this->assertSame($countsBefore['reports'], DB::table('research_reports')->count());
        $this->assertSame($countsBefore['claims'], DB::table('research_claims')->count());
        $this->assertSame($countsBefore['sources'], DB::table('sources')->count());
        $this->assertSame($statusBefore, $script->fresh()->status->value);
        $this->assertSame($versionNumberBefore, $script->fresh()->currentVersion->version);
    }

    public function test_evaluation_makes_no_external_http_requests(): void
    {
        Http::preventStrayRequests();
        [$project] = $this->projectWithReadyResearch();
        $script = $this->reviewReadyScript($project);

        $this->qualityService()->evaluateScript($script);

        $this->assertTrue(true);
    }

    public function test_evaluation_uses_bounded_query_count(): void
    {
        $project = ContentProject::factory()->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claims = ResearchClaim::factory()->count(3)->create([
            'research_report_id' => $report->id,
            'claim' => 'Cats purr to communicate with humans',
            'status' => ResearchClaimStatus::Supported,
            'importance' => ResearchClaimImportance::High,
        ]);
        foreach ($claims as $claim) {
            $claim->sources()->attach($source->id);
        }
        $script = $this->reviewReadyScript($project);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->qualityService()->evaluateScript($script);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(10, $queryCount);
    }
}
