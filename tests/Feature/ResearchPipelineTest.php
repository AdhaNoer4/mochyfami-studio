<?php

namespace Tests\Feature;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Source;
use App\Services\ResearchPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResearchPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function makeReport(): ResearchReport
    {
        return ResearchReport::factory()->create();
    }

    private function makeSource(ResearchReport $report): Source
    {
        return Source::factory()->create(['research_report_id' => $report->id]);
    }

    private function makeClaim(
        ResearchReport $report,
        ResearchClaimStatus $status,
        ResearchClaimImportance $importance = ResearchClaimImportance::High,
        bool $withEvidence = false
    ): ResearchClaim {
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'status' => $status,
            'importance' => $importance,
        ]);

        if ($withEvidence) {
            $source = $this->makeSource($report);
            $claim->sources()->attach($source->id);
        }

        return $claim;
    }

    /**
     * @return array{
     *     stage: string,
     *     stage_label: string,
     *     progress: int,
     *     ready_for_script: bool,
     *     next_actions: array<int, array{code: string, priority: string, message: string}>,
     *     summary: array<string, mixed>
     * }
     */
    private function evaluate(ResearchReport $report): array
    {
        return app(ResearchPipelineService::class)->evaluate($report);
    }

    public function test_empty_report_is_at_stage_empty_with_zero_progress(): void
    {
        $result = $this->evaluate($this->makeReport());

        $this->assertSame(ResearchPipelineService::STAGE_EMPTY, $result['stage']);
        $this->assertSame('Empty', $result['stage_label']);
        $this->assertSame(0, $result['progress']);
        $this->assertFalse($result['ready_for_script']);
        $this->assertSame(
            [ResearchPipelineService::ACTION_ADD_SOURCE, ResearchPipelineService::ACTION_ADD_CLAIM],
            array_column($result['next_actions'], 'code')
        );
        $this->assertSame(
            ['high', 'high'],
            array_column($result['next_actions'], 'priority')
        );
        $this->assertSame(0, $result['summary']['total_sources']);
        $this->assertSame(0, $result['summary']['total_claims']);
    }

    public function test_sources_without_claims_reaches_drafting_claims(): void
    {
        $report = $this->makeReport();
        $this->makeSource($report);

        $result = $this->evaluate($report);

        $this->assertSame(ResearchPipelineService::STAGE_DRAFTING_CLAIMS, $result['stage']);
        $this->assertSame(25, $result['progress']);
        $this->assertSame([ResearchPipelineService::ACTION_ADD_CLAIM], array_column($result['next_actions'], 'code'));
        $this->assertSame(1, $result['summary']['total_sources']);
        $this->assertSame(0, $result['summary']['total_claims']);
    }

    public function test_claims_without_sources_reaches_collecting_sources(): void
    {
        $report = $this->makeReport();
        $this->makeClaim($report, ResearchClaimStatus::Supported, ResearchClaimImportance::Low, false);

        $result = $this->evaluate($report);

        $this->assertSame(ResearchPipelineService::STAGE_COLLECTING_SOURCES, $result['stage']);
        $this->assertSame(25, $result['progress']);
        $this->assertSame(
            [ResearchPipelineService::ACTION_ADD_SOURCE, ResearchPipelineService::ACTION_LINK_EVIDENCE],
            array_column($result['next_actions'], 'code')
        );
        $this->assertSame(1, $result['summary']['total_claims']);
        $this->assertSame(0, $result['summary']['total_sources']);
    }

    public function test_claims_without_evidence_reaches_linking_evidence(): void
    {
        $report = $this->makeReport();
        $this->makeSource($report);
        $this->makeClaim($report, ResearchClaimStatus::Supported, ResearchClaimImportance::Low, false);

        $result = $this->evaluate($report);

        $this->assertSame(ResearchPipelineService::STAGE_LINKING_EVIDENCE, $result['stage']);
        $this->assertSame(50, $result['progress']);
        $this->assertSame(
            [ResearchPipelineService::ACTION_LINK_EVIDENCE],
            array_column($result['next_actions'], 'code')
        );
        $this->assertSame(1, $result['summary']['total_sources']);
        $this->assertSame(1, $result['summary']['total_claims']);
        $this->assertSame(1, $result['summary']['claims_without_evidence']);
    }

    public function test_fully_evidenced_but_unresolved_report_reaches_reviewing(): void
    {
        $report = $this->makeReport();
        $this->makeClaim($report, ResearchClaimStatus::Unverified, ResearchClaimImportance::High, true);

        $result = $this->evaluate($report);

        $this->assertSame(ResearchPipelineService::STAGE_REVIEWING, $result['stage']);
        $this->assertSame(75, $result['progress']);
        $this->assertFalse($result['ready_for_script']);
        $this->assertSame(
            [ResearchPipelineService::ACTION_VERIFY_CLAIMS],
            array_column($result['next_actions'], 'code')
        );
        $this->assertSame(1, $result['summary']['claims_with_evidence']);
        $this->assertSame(0, $result['summary']['claims_without_evidence']);
        $this->assertSame(1, $result['summary']['unresolved_claims']);
        $this->assertSame(
            ['sources' => 25, 'claims' => 25, 'evidence' => 25, 'quality' => 0],
            $result['summary']['progress_components']
        );
    }

    public function test_ready_report_reaches_ready_for_script_with_no_actions(): void
    {
        $report = $this->makeReport();
        $source = $this->makeSource($report);
        $claims = [
            $this->makeClaim($report, ResearchClaimStatus::Supported, ResearchClaimImportance::High, false),
            $this->makeClaim($report, ResearchClaimStatus::Supported, ResearchClaimImportance::Low, false),
        ];
        foreach ($claims as $claim) {
            $claim->sources()->attach($source->id);
        }

        $result = $this->evaluate($report);

        $this->assertSame(ResearchPipelineService::STAGE_READY_FOR_SCRIPT, $result['stage']);
        $this->assertSame('Ready for Script', $result['stage_label']);
        $this->assertSame(100, $result['progress']);
        $this->assertTrue($result['ready_for_script']);
        $this->assertSame([], $result['next_actions']);
    }

    public function test_contradicted_claim_triggers_resolution_action(): void
    {
        $report = $this->makeReport();
        $this->makeClaim($report, ResearchClaimStatus::Contradicted, ResearchClaimImportance::Low, true);

        $result = $this->evaluate($report);

        $this->assertFalse($result['ready_for_script']);
        $this->assertContains(
            ResearchPipelineService::ACTION_RESOLVE_CONTRADICTIONS,
            array_column($result['next_actions'], 'code')
        );
    }

    public function test_evaluation_does_not_mutate_database(): void
    {
        $report = $this->makeReport();
        $claim = $this->makeClaim($report, ResearchClaimStatus::Unverified, ResearchClaimImportance::High, true);

        $this->evaluate($report);

        $this->assertDatabaseCount('research_reports', 1);
        $this->assertDatabaseCount('research_claims', 1);
        $this->assertDatabaseCount('sources', 1);
        $this->assertDatabaseCount('research_claim_sources', 1);
        $this->assertDatabaseHas('research_claims', [
            'id' => $claim->id,
            'status' => ResearchClaimStatus::Unverified->value,
        ]);
    }

    public function test_evaluation_loads_evidence_without_n1(): void
    {
        $report = $this->makeReport();
        $claims = ResearchClaim::factory()
            ->count(3)
            ->create([
                'research_report_id' => $report->id,
                'status' => ResearchClaimStatus::Supported,
                'importance' => ResearchClaimImportance::High,
            ]);
        $sources = Source::factory()->count(3)->create(['research_report_id' => $report->id]);
        foreach ($claims as $index => $claim) {
            $claim->sources()->attach($sources[$index]->id);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $result = $this->evaluate($report);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertTrue($result['ready_for_script']);
        $this->assertSame(100, $result['progress']);
        $this->assertLessThanOrEqual(6, $queryCount);
    }
}
