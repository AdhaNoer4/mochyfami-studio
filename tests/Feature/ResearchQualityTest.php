<?php

namespace Tests\Feature;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Source;
use App\Services\ResearchQualityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResearchQualityTest extends TestCase
{
    use RefreshDatabase;

    private function makeReport(): ResearchReport
    {
        return ResearchReport::factory()->create();
    }

    private function makeClaim(
        ResearchReport $report,
        ResearchClaimStatus $status,
        ResearchClaimImportance $importance = ResearchClaimImportance::Medium,
        bool $withEvidence = false
    ): ResearchClaim {
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'status' => $status,
            'importance' => $importance,
        ]);

        if ($withEvidence) {
            $source = Source::factory()->create(['research_report_id' => $report->id]);
            $claim->sources()->attach($source->id);
        }

        return $claim;
    }

    public function test_report_with_no_claims_is_not_ready(): void
    {
        $result = app(ResearchQualityService::class)->evaluateReport($this->makeReport());

        $this->assertFalse($result['ready']);
        $this->assertSame(0, $result['score']);
        $this->assertSame(0, $result['summary']['total_claims']);
        $this->assertSame(
            'NO_CLAIMS',
            collect($result['issues'])->firstWhere('severity', 'blocker')['code']
        );
    }

    public function test_supported_claim_with_evidence_is_ready_with_full_score(): void
    {
        $report = $this->makeReport();
        $this->makeClaim($report, ResearchClaimStatus::Supported, ResearchClaimImportance::High, true);

        $result = app(ResearchQualityService::class)->evaluateReport($report);

        $this->assertTrue($result['ready']);
        $this->assertSame(100, $result['score']);
        $this->assertSame(1, $result['summary']['supported_claims']);
        $this->assertSame(1, $result['summary']['claims_with_evidence']);
        $this->assertEmpty($result['issues']);
    }

    public function test_supported_claim_without_evidence_blocks_the_gate(): void
    {
        $report = $this->makeReport();
        $claim = $this->makeClaim($report, ResearchClaimStatus::Supported, ResearchClaimImportance::High, false);

        $result = app(ResearchQualityService::class)->evaluateReport($report);

        $this->assertFalse($result['ready']);
        $this->assertSame(0, $result['score']);
        $issue = collect($result['issues'])->firstWhere('code', 'CLAIM_WITHOUT_EVIDENCE');
        $this->assertNotNull($issue);
        $this->assertSame('blocker', $issue['severity']);
        $this->assertSame($claim->id, $issue['claim_id']);
        $this->assertSame(1, $result['summary']['claims_without_evidence']);
    }

    public function test_important_unverified_claim_blocks_the_gate(): void
    {
        $report = $this->makeReport();
        $claim = $this->makeClaim($report, ResearchClaimStatus::Unverified, ResearchClaimImportance::High, true);

        $result = app(ResearchQualityService::class)->evaluateReport($report);

        $this->assertFalse($result['ready']);
        $issue = collect($result['issues'])->firstWhere('code', 'IMPORTANT_CLAIM_UNVERIFIED');
        $this->assertNotNull($issue);
        $this->assertSame('blocker', $issue['severity']);
        $this->assertSame($claim->id, $issue['claim_id']);
    }

    public function test_important_uncertain_claim_blocks_the_gate(): void
    {
        $report = $this->makeReport();
        $this->makeClaim($report, ResearchClaimStatus::Uncertain, ResearchClaimImportance::High, true);

        $result = app(ResearchQualityService::class)->evaluateReport($report);

        $this->assertFalse($result['ready']);
        $this->assertSame(
            'IMPORTANT_CLAIM_UNCERTAIN',
            collect($result['issues'])->firstWhere('severity', 'blocker')['code']
        );
    }

    public function test_important_contradicted_claim_blocks_the_gate(): void
    {
        $report = $this->makeReport();
        $this->makeClaim($report, ResearchClaimStatus::Contradicted, ResearchClaimImportance::High, true);

        $result = app(ResearchQualityService::class)->evaluateReport($report);

        $this->assertFalse($result['ready']);
        $this->assertSame(
            'IMPORTANT_CLAIM_CONTRADICTED',
            collect($result['issues'])->firstWhere('severity', 'blocker')['code']
        );
    }

    public function test_any_contradicted_claim_blocks_even_when_not_important(): void
    {
        $report = $this->makeReport();
        $this->makeClaim($report, ResearchClaimStatus::Contradicted, ResearchClaimImportance::Low, true);

        $result = app(ResearchQualityService::class)->evaluateReport($report);

        $this->assertFalse($result['ready']);
        $this->assertSame(
            'CLAIM_CONTRADICTED',
            collect($result['issues'])->firstWhere('severity', 'blocker')['code']
        );
    }

    public function test_non_important_uncertain_claim_only_warns(): void
    {
        $report = $this->makeReport();
        $claim = $this->makeClaim($report, ResearchClaimStatus::Uncertain, ResearchClaimImportance::Medium, true);

        $result = app(ResearchQualityService::class)->evaluateReport($report);

        $this->assertTrue($result['ready']);
        $issue = collect($result['issues'])->firstWhere('code', 'CLAIM_UNCERTAIN');
        $this->assertNotNull($issue);
        $this->assertSame('warning', $issue['severity']);
        $this->assertSame($claim->id, $issue['claim_id']);
    }

    public function test_non_important_unverified_claim_only_warns(): void
    {
        $report = $this->makeReport();
        $this->makeClaim($report, ResearchClaimStatus::Unverified, ResearchClaimImportance::Low, true);

        $result = app(ResearchQualityService::class)->evaluateReport($report);

        $this->assertTrue($result['ready']);
        $this->assertSame(
            'CLAIM_UNVERIFIED',
            collect($result['issues'])->firstWhere('severity', 'warning')['code']
        );
    }

    public function test_medium_importance_does_not_count_as_important(): void
    {
        $report = $this->makeReport();
        $this->makeClaim($report, ResearchClaimStatus::Unverified, ResearchClaimImportance::Medium, true);

        $result = app(ResearchQualityService::class)->evaluateReport($report);

        $this->assertTrue($result['ready']);
        $this->assertSame(0, $result['summary']['important_claims']);
        $this->assertFalse(
            collect($result['issues'])->contains('code', 'IMPORTANT_CLAIM_UNVERIFIED')
        );
    }

    public function test_multiple_evidence_sources_count_as_single_evidence(): void
    {
        $report = $this->makeReport();
        $claim = $this->makeClaim($report, ResearchClaimStatus::Supported, ResearchClaimImportance::High, false);
        $sources = Source::factory()->count(3)->create(['research_report_id' => $report->id]);
        $claim->sources()->attach($sources->pluck('id')->all());

        $result = app(ResearchQualityService::class)->evaluateReport($report);

        $this->assertTrue($result['ready']);
        $this->assertSame(100, $result['score']);
        $this->assertSame(1, $result['summary']['claims_with_evidence']);
        $this->assertSame(0, $result['summary']['claims_without_evidence']);
        $this->assertEmpty($result['issues']);
    }

    public function test_summary_and_score_reflect_mixed_report(): void
    {
        $report = $this->makeReport();
        $this->makeClaim($report, ResearchClaimStatus::Supported, ResearchClaimImportance::High, true);
        $this->makeClaim($report, ResearchClaimStatus::Supported, ResearchClaimImportance::Low, true);
        $this->makeClaim($report, ResearchClaimStatus::Unverified, ResearchClaimImportance::Low, true);
        $this->makeClaim($report, ResearchClaimStatus::Supported, ResearchClaimImportance::Medium, false);

        $result = app(ResearchQualityService::class)->evaluateReport($report);

        $this->assertSame(4, $result['summary']['total_claims']);
        $this->assertSame(3, $result['summary']['supported_claims']);
        $this->assertSame(1, $result['summary']['unverified_claims']);
        $this->assertSame(1, $result['summary']['important_claims']);
        $this->assertSame(1, $result['summary']['important_claims_ready']);
        $this->assertSame(3, $result['summary']['claims_with_evidence']);
        $this->assertSame(1, $result['summary']['claims_without_evidence']);
        $this->assertSame(75, $result['score']);
        $this->assertFalse($result['ready']);
        $this->assertSame(
            'CLAIM_WITHOUT_EVIDENCE',
            collect($result['issues'])->firstWhere('severity', 'blocker')['code']
        );
    }

    public function test_evaluation_does_not_mutate_database(): void
    {
        $report = $this->makeReport();
        $claim = $this->makeClaim($report, ResearchClaimStatus::Unverified, ResearchClaimImportance::High, true);

        app(ResearchQualityService::class)->evaluateReport($report);

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

        $result = app(ResearchQualityService::class)->evaluateReport($report);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertTrue($result['ready']);
        $this->assertSame(100, $result['score']);
        $this->assertLessThanOrEqual(5, $queryCount);
    }
}
