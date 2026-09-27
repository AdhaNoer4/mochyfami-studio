<?php

namespace Tests\Feature;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Enums\ResearchStatus;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Source;
use App\Services\Research\DTO\ResearchToScriptContext;
use App\Services\Research\ResearchToScriptContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResearchToScriptContextTest extends TestCase
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
        ResearchClaimImportance $importance,
        bool $withEvidence
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

    private function build(ResearchReport $report): ResearchToScriptContext
    {
        return app(ResearchToScriptContextService::class)->build($report);
    }

    public function test_empty_report_yields_an_empty_context(): void
    {
        $context = $this->build($this->makeReport());

        $data = $context->toArray();

        $this->assertSame('pending', $data['report']['status']);
        $this->assertFalse($data['report']['quality_ready']);
        $this->assertSame(0, $data['report']['counts']['total_claims']);
        $this->assertSame([], $data['claims']);
        $this->assertSame([], $data['usable_claims']);
        $this->assertSame([], $data['claims_requiring_verification']);
        $this->assertSame([], $data['contradicted_claims']);
        $this->assertSame([], $data['unsupported_claims']);
        $this->assertFalse($data['quality']['ready']);
        $this->assertSame(0, $data['quality']['score']);
    }

    public function test_supported_claim_with_evidence_is_usable(): void
    {
        $report = $this->makeReport();
        $this->makeClaim($report, ResearchClaimStatus::Supported, ResearchClaimImportance::High, true);

        $context = $this->build($report);

        $data = $context->toArray();
        $this->assertTrue($data['report']['quality_ready']);
        $this->assertSame(1, $data['report']['counts']['usable_claims']);
        $this->assertSame(1, $data['report']['counts']['evidence_backed_claims']);
        $this->assertSame(ResearchToScriptContext::CLASSIFICATION_USABLE, $data['usable_claims'][0]['classification']);
        $this->assertTrue($data['usable_claims'][0]['has_evidence']);
        $this->assertCount(1, $data['usable_claims'][0]['sources']);
        $this->assertTrue($data['quality']['ready']);
        $this->assertSame(100, $data['quality']['score']);
        $this->assertSame('ready_for_script', $data['pipeline']['stage']);
    }

    public function test_supported_claim_without_evidence_is_unsupported(): void
    {
        $report = $this->makeReport();
        $this->makeClaim($report, ResearchClaimStatus::Supported, ResearchClaimImportance::Low, false);

        $context = $this->build($report);

        $data = $context->toArray();
        $this->assertFalse($data['report']['quality_ready']);
        $this->assertSame(ResearchToScriptContext::CLASSIFICATION_UNSUPPORTED, $data['unsupported_claims'][0]['classification']);
        $this->assertFalse($data['unsupported_claims'][0]['has_evidence']);
        $this->assertSame([], $data['unsupported_claims'][0]['sources']);
        $this->assertSame([], $data['usable_claims']);
        $this->assertContains(
            'CLAIM_WITHOUT_EVIDENCE',
            array_column($data['quality']['blockers'], 'code')
        );
    }

    public function test_unverified_and_uncertain_claims_require_verification(): void
    {
        $report = $this->makeReport();
        $this->makeClaim($report, ResearchClaimStatus::Unverified, ResearchClaimImportance::High, true);
        $this->makeClaim($report, ResearchClaimStatus::Uncertain, ResearchClaimImportance::Low, true);

        $context = $this->build($report);

        $data = $context->toArray();
        $this->assertSame(2, $data['report']['counts']['claims_requiring_verification']);
        $this->assertSame([], $data['usable_claims']);
        $this->assertSame(
            [ResearchToScriptContext::CLASSIFICATION_REQUIRES_VERIFICATION, ResearchToScriptContext::CLASSIFICATION_REQUIRES_VERIFICATION],
            array_column($data['claims_requiring_verification'], 'classification')
        );
        $this->assertFalse($data['quality']['ready']);
        $this->assertContains('IMPORTANT_CLAIM_UNVERIFIED', array_column($data['quality']['blockers'], 'code'));
        $this->assertContains('CLAIM_UNCERTAIN', array_column($data['quality']['warnings'], 'code'));
        $this->assertContains('VERIFY_CLAIMS', array_column($data['pipeline']['next_actions'], 'code'));
    }

    public function test_contradicted_claim_is_never_usable(): void
    {
        $report = $this->makeReport();
        $this->makeClaim($report, ResearchClaimStatus::Contradicted, ResearchClaimImportance::High, true);

        $context = $this->build($report);

        $data = $context->toArray();
        $this->assertSame(ResearchToScriptContext::CLASSIFICATION_CONTRADICTED, $data['contradicted_claims'][0]['classification']);
        $this->assertSame([], $data['usable_claims']);
        $this->assertFalse($data['quality']['ready']);
        $this->assertContains('IMPORTANT_CLAIM_CONTRADICTED', array_column($data['quality']['blockers'], 'code'));
        $this->assertContains('RESOLVE_CONTRADICTIONS', array_column($data['pipeline']['next_actions'], 'code'));
    }

    public function test_claims_are_sorted_by_id_deterministically(): void
    {
        $report = $this->makeReport();
        $first = $this->makeClaim($report, ResearchClaimStatus::Supported, ResearchClaimImportance::Low, true);
        $second = $this->makeClaim($report, ResearchClaimStatus::Unverified, ResearchClaimImportance::Low, true);

        $context = $this->build($report);

        $this->assertSame([$first->id, $second->id], array_column($context->claims, 'id'));
    }

    public function test_build_is_deterministic(): void
    {
        $report = $this->makeReport();
        $this->makeClaim($report, ResearchClaimStatus::Supported, ResearchClaimImportance::High, true);
        $this->makeClaim($report, ResearchClaimStatus::Unverified, ResearchClaimImportance::Low, true);

        $first = $this->build($report->fresh())->toArray();
        $second = $this->build($report->fresh())->toArray();

        $this->assertSame($first, $second);
    }

    public function test_build_does_not_mutate_database(): void
    {
        $report = $this->makeReport();
        $claim = $this->makeClaim($report, ResearchClaimStatus::Unverified, ResearchClaimImportance::High, true);

        $this->build($report);

        $this->assertDatabaseCount('research_reports', 1);
        $this->assertDatabaseCount('research_claims', 1);
        $this->assertDatabaseCount('sources', 1);
        $this->assertDatabaseCount('research_claim_sources', 1);
        $this->assertDatabaseHas('research_reports', [
            'id' => $report->id,
            'status' => ResearchStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('research_claims', [
            'id' => $claim->id,
            'status' => ResearchClaimStatus::Unverified->value,
        ]);
    }

    public function test_build_stays_within_query_budget(): void
    {
        $report = $this->makeReport();
        $claims = ResearchClaim::factory()->count(3)->create([
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

        $this->build($report->fresh());

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(8, $queryCount);
    }

    public function test_build_makes_zero_network_requests(): void
    {
        Http::preventStrayRequests();

        $report = $this->makeReport();
        $this->makeClaim($report, ResearchClaimStatus::Supported, ResearchClaimImportance::High, true);

        $this->build($report);

        $this->assertTrue(true);
    }
}
