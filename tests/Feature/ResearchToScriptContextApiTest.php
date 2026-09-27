<?php

namespace Tests\Feature;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Enums\ResearchStatus;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Source;
use App\Models\User;
use App\Services\Research\DTO\ResearchToScriptContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResearchToScriptContextApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function contextUrl(ContentProject $project): string
    {
        return "/api/v1/projects/{$project->id}/research/script-context";
    }

    private function report(ContentProject $project): ResearchReport
    {
        return ResearchReport::factory()->create(['content_project_id' => $project->id]);
    }

    private function readyReport(ContentProject $project): ResearchReport
    {
        $report = $this->report($project);
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'claim' => 'Cats purr to communicate with humans',
            'status' => ResearchClaimStatus::Supported,
            'importance' => ResearchClaimImportance::High,
        ]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim->sources()->attach($source->id);

        return $report;
    }

    public function test_guest_is_rejected(): void
    {
        $project = ContentProject::factory()->create();
        $this->readyReport($project);

        $this->getJson($this->contextUrl($project))->assertStatus(401);
    }

    public function test_returns_404_when_project_has_no_report(): void
    {
        $project = ContentProject::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->contextUrl($project))
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Research report not found for this project.');
    }

    public function test_returns_404_for_foreign_project(): void
    {
        $project = ContentProject::factory()->create();
        $otherProject = ContentProject::factory()->create();
        $this->readyReport($otherProject);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->contextUrl($project))
            ->assertNotFound();
    }

    public function test_ready_report_returns_full_context_shape(): void
    {
        $project = ContentProject::factory()->create();
        $this->readyReport($project);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->contextUrl($project));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.report.quality_ready', true)
            ->assertJsonPath('data.report.counts.total_claims', 1)
            ->assertJsonPath('data.report.counts.usable_claims', 1)
            ->assertJsonPath('data.report.counts.evidence_backed_claims', 1)
            ->assertJsonPath('data.quality.ready', true)
            ->assertJsonPath('data.quality.score', 100)
            ->assertJsonPath('data.pipeline.stage', 'ready_for_script')
            ->assertJsonPath('data.pipeline.progress', 100)
            ->assertJsonCount(1, 'data.usable_claims')
            ->assertJsonCount(0, 'data.claims_requiring_verification')
            ->assertJsonCount(0, 'data.contradicted_claims')
            ->assertJsonCount(0, 'data.unsupported_claims')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'report' => [
                        'id',
                        'summary',
                        'status',
                        'quality_ready',
                        'counts' => [
                            'total_claims',
                            'usable_claims',
                            'claims_requiring_verification',
                            'contradicted_claims',
                            'unsupported_claims',
                            'evidence_backed_claims',
                        ],
                    ],
                    'claims' => [
                        [
                            'id',
                            'claim',
                            'importance',
                            'status',
                            'classification',
                            'has_evidence',
                            'sources' => [['id', 'title', 'domain', 'url', 'source_type']],
                        ],
                    ],
                    'usable_claims',
                    'claims_requiring_verification',
                    'contradicted_claims',
                    'unsupported_claims',
                    'quality' => ['ready', 'score', 'summary', 'blockers', 'warnings'],
                    'pipeline' => ['stage', 'stage_label', 'progress', 'ready_for_script', 'next_actions'],
                ],
                'message',
            ]);
    }

    public function test_contradicted_claims_are_classified_but_never_usable(): void
    {
        $project = ContentProject::factory()->create();
        $report = $this->report($project);
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'claim' => 'Cats chirp at birds',
            'status' => ResearchClaimStatus::Contradicted,
            'importance' => ResearchClaimImportance::High,
        ]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim->sources()->attach($source->id);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->contextUrl($project));

        $response->assertOk()
            ->assertJsonPath('data.report.quality_ready', false)
            ->assertJsonPath('data.contradicted_claims.0.classification', ResearchToScriptContext::CLASSIFICATION_CONTRADICTED)
            ->assertJsonPath('data.contradicted_claims.0.claim', 'Cats chirp at birds')
            ->assertJsonPath('data.usable_claims', [])
            ->assertJsonPath('data.pipeline.next_actions.0.code', 'RESOLVE_CONTRADICTIONS');
    }

    public function test_unverified_claims_require_verification(): void
    {
        $project = ContentProject::factory()->create();
        $report = $this->report($project);
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'claim' => 'Cats dream in color',
            'status' => ResearchClaimStatus::Unverified,
            'importance' => ResearchClaimImportance::High,
        ]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim->sources()->attach($source->id);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson($this->contextUrl($project));

        $response->assertOk()
            ->assertJsonPath('data.claims_requiring_verification.0.classification', ResearchToScriptContext::CLASSIFICATION_REQUIRES_VERIFICATION)
            ->assertJsonPath('data.usable_claims', [])
            ->assertJsonPath('data.quality.ready', false)
            ->assertJsonPath('data.quality.blockers.0.code', 'IMPORTANT_CLAIM_UNVERIFIED');
    }

    public function test_context_never_mutates_database(): void
    {
        $project = ContentProject::factory()->create();
        $report = $this->report($project);
        ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'status' => ResearchClaimStatus::Contradicted,
            'importance' => ResearchClaimImportance::High,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->contextUrl($project))
            ->assertOk();

        $this->assertDatabaseCount('research_reports', 1);
        $this->assertDatabaseCount('research_claims', 1);
        $this->assertDatabaseCount('sources', 0);
        $this->assertDatabaseCount('research_claim_sources', 0);
        $this->assertDatabaseCount('ai_generations', 0);
        $this->assertDatabaseHas('research_reports', [
            'id' => $report->id,
            'status' => ResearchStatus::Pending->value,
        ]);
    }

    public function test_context_stays_within_query_budget(): void
    {
        $project = ContentProject::factory()->create();
        $report = $this->readyReport($project);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->contextUrl($project))
            ->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(8, $queryCount);
        $this->assertNotNull($report);
    }

    public function test_context_makes_zero_network_requests(): void
    {
        Http::preventStrayRequests();

        $project = ContentProject::factory()->create();
        $this->readyReport($project);

        $this->actingAs($this->user, 'sanctum')
            ->getJson($this->contextUrl($project))
            ->assertOk();
    }

    public function test_context_response_is_deterministic(): void
    {
        $project = ContentProject::factory()->create();
        $this->readyReport($project);

        $url = $this->contextUrl($project);
        $first = $this->actingAs($this->user, 'sanctum')->getJson($url)->json('data');
        $second = $this->actingAs($this->user, 'sanctum')->getJson($url)->json('data');

        $this->assertSame($first, $second);
    }
}
