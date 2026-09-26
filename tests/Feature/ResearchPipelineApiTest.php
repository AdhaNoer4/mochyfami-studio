<?php

namespace Tests\Feature;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Source;
use App\Models\User;
use App\Services\ResearchPipelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResearchPipelineApiTest extends TestCase
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

    private function readyReport(ContentProject $project): ResearchReport
    {
        $report = $this->report($project);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claims = ResearchClaim::factory()->count(2)->create([
            'research_report_id' => $report->id,
            'status' => ResearchClaimStatus::Supported,
            'importance' => ResearchClaimImportance::High,
        ]);
        foreach ($claims as $claim) {
            $claim->sources()->attach($source->id);
        }

        return $report;
    }

    public function test_owner_can_read_pipeline_for_empty_report(): void
    {
        $project = $this->project();
        $this->report($project);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research/pipeline");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stage', ResearchPipelineService::STAGE_EMPTY)
            ->assertJsonPath('data.stage_label', 'Empty')
            ->assertJsonPath('data.progress', 0)
            ->assertJsonPath('data.ready_for_script', false)
            ->assertJsonPath('data.summary.total_sources', 0)
            ->assertJsonPath('data.summary.total_claims', 0)
            ->assertJsonPath('data.summary.progress_components.sources', 0)
            ->assertJsonPath('data.summary.progress_components.claims', 0)
            ->assertJsonCount(2, 'data.next_actions')
            ->assertJsonPath('data.next_actions.0.code', ResearchPipelineService::ACTION_ADD_SOURCE)
            ->assertJsonPath('data.next_actions.1.code', ResearchPipelineService::ACTION_ADD_CLAIM);
    }

    public function test_pipeline_reports_ready_state(): void
    {
        $project = $this->project();
        $this->readyReport($project);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research/pipeline");

        $response->assertOk()
            ->assertJsonPath('data.stage', ResearchPipelineService::STAGE_READY_FOR_SCRIPT)
            ->assertJsonPath('data.progress', 100)
            ->assertJsonPath('data.ready_for_script', true)
            ->assertJsonPath('data.summary.total_sources', 1)
            ->assertJsonPath('data.summary.total_claims', 2)
            ->assertJsonPath('data.summary.claims_with_evidence', 2)
            ->assertJsonPath('data.summary.claims_without_evidence', 0)
            ->assertJsonPath('data.summary.unresolved_claims', 0)
            ->assertJsonCount(0, 'data.next_actions');
    }

    public function test_pipeline_requires_authentication(): void
    {
        $project = $this->project();
        $this->report($project);

        $this->getJson("/api/v1/projects/{$project->id}/research/pipeline")
            ->assertStatus(401);
    }

    public function test_pipeline_returns_404_when_project_has_no_report(): void
    {
        $project = $this->project();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research/pipeline");

        $response->assertNotFound()
            ->assertJsonPath('message', 'Research report not found for this project.');
    }

    public function test_pipeline_returns_404_for_foreign_project(): void
    {
        $project = $this->project();
        $otherProject = $this->project();
        $this->report($otherProject);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research/pipeline");

        $response->assertNotFound();
    }

    public function test_pipeline_never_mutates_database(): void
    {
        $project = $this->project();
        $this->report($project);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research/pipeline")
            ->assertOk();

        $this->assertDatabaseCount('research_reports', 1);
        $this->assertDatabaseCount('research_claims', 0);
        $this->assertDatabaseCount('sources', 0);
        $this->assertDatabaseCount('research_claim_sources', 0);
    }

    public function test_pipeline_stays_within_query_budget(): void
    {
        $project = $this->project();
        $this->readyReport($project);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research/pipeline")
            ->assertOk();

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(7, $queryCount);
    }

    public function test_pipeline_makes_zero_network_requests(): void
    {
        Http::preventStrayRequests();

        $project = $this->project();
        $this->report($project);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research/pipeline")
            ->assertOk();
    }

    public function test_claim_evidence_updates_pipeline_progress(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'status' => ResearchClaimStatus::Supported->value,
            'importance' => ResearchClaimImportance::High->value,
        ]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research/pipeline")
            ->assertOk()
            ->assertJsonPath('data.stage', ResearchPipelineService::STAGE_LINKING_EVIDENCE)
            ->assertJsonPath('data.progress', 50)
            ->assertJsonPath('data.summary.claims_without_evidence', 1);

        $claim->sources()->attach($source->id);

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research/pipeline")
            ->assertOk()
            ->assertJsonPath('data.stage', ResearchPipelineService::STAGE_READY_FOR_SCRIPT)
            ->assertJsonPath('data.progress', 100)
            ->assertJsonPath('data.summary.claims_without_evidence', 0);
    }
}
