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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResearchQualityApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function project(): ContentProject
    {
        return ContentProject::factory()->create();
    }

    private function report(ContentProject $project): ResearchReport
    {
        return ResearchReport::factory()->create(['content_project_id' => $project->id]);
    }

    private function supportedWithEvidence(ResearchReport $report): void
    {
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'status' => ResearchClaimStatus::Supported,
            'importance' => ResearchClaimImportance::High,
        ]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim->sources()->attach($source->id);
    }

    public function test_can_get_quality_for_ready_report(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $this->supportedWithEvidence($report);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research/quality");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.score', 100)
            ->assertJsonPath('data.summary.total_claims', 1)
            ->assertJsonCount(0, 'data.issues');
    }

    public function test_quality_reports_not_ready_when_claims_are_unresolved(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'status' => ResearchClaimStatus::Unverified,
            'importance' => ResearchClaimImportance::High,
        ]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim->sources()->attach($source->id);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research/quality");

        $response->assertOk()
            ->assertJsonPath('data.ready', false)
            ->assertJsonPath('data.issues.0.code', 'IMPORTANT_CLAIM_UNVERIFIED')
            ->assertJsonPath('data.issues.0.severity', 'blocker');
    }

    public function test_quality_counts_multiple_evidence_sources_once(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'status' => ResearchClaimStatus::Supported,
            'importance' => ResearchClaimImportance::Low,
        ]);
        $sources = Source::factory()->count(2)->create(['research_report_id' => $report->id]);
        $claim->sources()->attach($sources->pluck('id')->all());

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research/quality");

        $response->assertOk()
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.summary.claims_with_evidence', 1)
            ->assertJsonCount(0, 'data.issues');
    }

    public function test_quality_response_contains_full_summary_shape(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'status' => ResearchClaimStatus::Uncertain,
            'importance' => ResearchClaimImportance::Low,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research/quality");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'ready',
                    'score',
                    'summary' => [
                        'total_claims',
                        'supported_claims',
                        'unverified_claims',
                        'uncertain_claims',
                        'contradicted_claims',
                        'claims_with_evidence',
                        'claims_without_evidence',
                        'important_claims',
                        'important_claims_ready',
                    ],
                    'issues' => [
                        ['code', 'severity', 'claim_id', 'message'],
                    ],
                ],
                'message',
            ]);
    }

    public function test_quality_returns_404_when_project_has_no_report(): void
    {
        $project = $this->project();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research/quality");

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Research report not found for this project.');
    }

    public function test_quality_returns_404_for_foreign_project(): void
    {
        $project = $this->project();
        $otherProject = $this->project();
        $this->supportedWithEvidence($this->report($otherProject));

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research/quality");

        $response->assertNotFound()
            ->assertJsonPath('message', 'Research report not found for this project.');
    }

    public function test_quality_requires_authentication(): void
    {
        $project = $this->project();
        $this->supportedWithEvidence($this->report($project));

        $this->getJson("/api/v1/projects/{$project->id}/research/quality")
            ->assertStatus(401);
    }

    public function test_quality_endpoint_does_not_mutate_state(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'status' => ResearchClaimStatus::Uncertain,
            'importance' => ResearchClaimImportance::High,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research/quality")
            ->assertOk();

        $this->assertDatabaseCount('research_reports', 1);
        $this->assertDatabaseCount('research_claims', 1);
        $this->assertDatabaseHas('research_reports', [
            'id' => $report->id,
            'status' => ResearchStatus::Pending->value,
        ]);
    }

    public function test_quality_query_count_stays_bounded(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $claims = ResearchClaim::factory()
            ->count(3)
            ->create([
                'research_report_id' => $report->id,
                'status' => ResearchClaimStatus::Supported,
                'importance' => ResearchClaimImportance::Medium,
            ]);
        $sources = Source::factory()->count(3)->create(['research_report_id' => $report->id]);
        foreach ($claims as $index => $claim) {
            $claim->sources()->attach($sources[$index]->id);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research/quality")
            ->assertOk()
            ->assertJsonPath('data.ready', true);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(7, $queryCount);
    }
}
