<?php

namespace Tests\Feature;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Enums\ResearchStatus;
use App\Enums\SourceType;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchApiTest extends TestCase
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

    private function report(ContentProject $project, ?ResearchStatus $status = null): ResearchReport
    {
        return ResearchReport::factory()->create([
            'content_project_id' => $project->id,
            'status' => $status ?? ResearchStatus::Pending,
        ]);
    }

    private function source(ResearchReport $report): Source
    {
        return Source::factory()->create(['research_report_id' => $report->id]);
    }

    private function claim(ResearchReport $report): ResearchClaim
    {
        return ResearchClaim::factory()->create(['research_report_id' => $report->id]);
    }

    public function test_can_create_research_report(): void
    {
        $project = $this->project();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/research");

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Research report created successfully.')
            ->assertJsonPath('data.project_id', $project->id)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.status_label', 'Pending')
            ->assertJsonPath('data.summary', null)
            ->assertJsonPath('data.researched_at', null)
            ->assertJsonCount(0, 'data.claims')
            ->assertJsonCount(0, 'data.sources');

        $this->assertDatabaseHas('research_reports', [
            'content_project_id' => $project->id,
            'status' => 'pending',
        ]);
    }

    public function test_research_resource_does_not_expose_internal_columns(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $this->source($report);
        $this->claim($report);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research");

        $response->assertOk()
            ->assertJsonMissingPath('data.content_project_id')
            ->assertJsonMissingPath('data.created_by')
            ->assertJsonPath('data.project_id', $project->id);
    }

    public function test_duplicate_research_report_returns_409(): void
    {
        $project = $this->project();
        $this->report($project);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/research");

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This project already has a research report.');

        $this->assertDatabaseCount('research_reports', 1);
    }

    public function test_cannot_create_research_for_missing_project(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/projects/99999/research');

        $response->assertNotFound();
    }

    public function test_get_research_returns_null_when_no_report_exists(): void
    {
        $project = $this->project();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'No research report yet for this project.');
    }

    public function test_can_get_research_with_claims_and_sources(): void
    {
        $project = $this->project();
        $report = $this->report($project, ResearchStatus::Researching);
        $this->source($report);
        $this->claim($report);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research");

        $response->assertOk()
            ->assertJsonPath('data.status', 'researching')
            ->assertJsonCount(1, 'data.sources')
            ->assertJsonCount(1, 'data.claims');
    }

    public function test_can_update_research_summary_and_date(): void
    {
        $project = $this->project();
        $this->report($project);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/research", [
                'summary' => 'All key facts verified against primary sources.',
                'researched_at' => '2026-09-25',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Research report updated successfully.')
            ->assertJsonPath('data.summary', 'All key facts verified against primary sources.');

        $this->assertDatabaseHas('research_reports', [
            'content_project_id' => $project->id,
            'summary' => 'All key facts verified against primary sources.',
        ]);
    }

    public function test_cannot_update_research_when_no_report_exists(): void
    {
        $project = $this->project();

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/research", ['summary' => 'Nope']);

        $response->assertNotFound();
    }

    public function test_can_delete_research_report(): void
    {
        $project = $this->project();
        $report = $this->report($project);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/research");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Research report deleted successfully.');

        $this->assertDatabaseMissing('research_reports', ['id' => $report->id]);
    }

    public function test_cannot_delete_research_when_no_report_exists(): void
    {
        $project = $this->project();

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/research");

        $response->assertNotFound();
    }

    public function test_cannot_create_research_unauthenticated(): void
    {
        $project = $this->project();

        $response = $this->postJson("/api/v1/projects/{$project->id}/research");

        $response->assertStatus(401);
    }

    public function test_pending_report_exposes_expected_transition_action(): void
    {
        $project = $this->project();
        $this->report($project);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research");

        $response->assertOk()
            ->assertJsonCount(1, 'data.allowed_transitions')
            ->assertJsonPath('data.allowed_transitions.0.status', 'researching')
            ->assertJsonPath('data.allowed_transitions.0.action', 'Start Research')
            ->assertJsonPath('data.allowed_transitions.0.destructive', false);
    }

    public function test_can_list_sources(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $this->source($report);
        $this->source($report);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research/sources");

        $response->assertOk()
            ->assertJsonCount(2, 'data.items');
    }

    public function test_can_create_source(): void
    {
        $project = $this->project();
        $this->report($project);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/research/sources", [
                'title' => 'NASA Evidence',
                'url' => 'https://www.nasa.gov/evidence',
                'domain' => 'nasa.gov',
                'source_type' => SourceType::Article->value,
            ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Source created successfully.')
            ->assertJsonPath('data.title', 'NASA Evidence')
            ->assertJsonPath('data.domain', 'nasa.gov')
            ->assertJsonPath('data.source_type_label', 'Article');

        $this->assertDatabaseHas('sources', [
            'title' => 'NASA Evidence',
            'url' => 'https://www.nasa.gov/evidence',
        ]);
    }

    public function test_source_url_must_be_valid(): void
    {
        $project = $this->project();
        $this->report($project);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/research/sources", [
                'title' => 'Broken Link',
                'url' => 'not-a-valid-url',
                'source_type' => SourceType::Article->value,
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['url']);
    }

    public function test_source_domain_must_be_valid_hostname(): void
    {
        $project = $this->project();
        $this->report($project);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/research/sources", [
                'title' => 'Bad Domain',
                'url' => 'https://example.com/article',
                'domain' => 'https://nasa.gov',
                'source_type' => SourceType::Article->value,
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['domain']);
    }

    public function test_source_type_must_be_valid(): void
    {
        $project = $this->project();
        $this->report($project);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/research/sources", [
                'title' => 'Weird Source',
                'url' => 'https://example.com/article',
                'source_type' => 'wikipedia',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['source_type']);
    }

    public function test_can_update_source(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $source = $this->source($report);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/research/sources/{$source->id}", [
                'title' => 'Updated Title',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Source updated successfully.')
            ->assertJsonPath('data.title', 'Updated Title');

        $this->assertDatabaseHas('sources', [
            'id' => $source->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_can_delete_source(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $source = $this->source($report);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/research/sources/{$source->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Source deleted successfully.');

        $this->assertDatabaseMissing('sources', ['id' => $source->id]);
    }

    public function test_source_from_another_project_report_returns_404(): void
    {
        $project = $this->project();
        $otherProject = $this->project();
        $this->report($project);
        $otherReport = $this->report($otherProject);
        $otherSource = $this->source($otherReport);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/research/sources/{$otherSource->id}");

        $response->assertNotFound();

        $this->assertDatabaseHas('sources', ['id' => $otherSource->id]);
    }

    public function test_cannot_create_source_unauthenticated(): void
    {
        $project = $this->project();

        $response = $this->postJson("/api/v1/projects/{$project->id}/research/sources", []);

        $response->assertStatus(401);
    }

    public function test_can_create_claim_with_defaults(): void
    {
        $project = $this->project();
        $this->report($project);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/research/claims", [
                'claim' => 'The Great Pyramid aligns within 0.05 degrees of true north.',
            ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Claim created successfully.')
            ->assertJsonPath('data.status', 'unverified')
            ->assertJsonPath('data.importance', 'medium');

        $this->assertDatabaseHas('research_claims', [
            'claim' => 'The Great Pyramid aligns within 0.05 degrees of true north.',
            'status' => 'unverified',
            'importance' => 'medium',
        ]);
    }

    public function test_can_create_claim_with_explicit_status_and_importance(): void
    {
        $project = $this->project();
        $this->report($project);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/research/claims", [
                'claim' => 'Water boils at 100 degrees Celsius at sea level.',
                'status' => ResearchClaimStatus::Supported->value,
                'importance' => ResearchClaimImportance::High->value,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'supported')
            ->assertJsonPath('data.status_label', 'Supported')
            ->assertJsonPath('data.importance', 'high')
            ->assertJsonPath('data.importance_label', 'High');
    }

    public function test_claim_status_must_be_valid(): void
    {
        $project = $this->project();
        $this->report($project);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/research/claims", [
                'claim' => 'Some claim',
                'status' => 'pending',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    public function test_claim_importance_must_be_valid(): void
    {
        $project = $this->project();
        $this->report($project);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/research/claims", [
                'claim' => 'Some claim',
                'importance' => 'critical',
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['importance']);
    }

    public function test_can_list_claims(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $this->claim($report);
        $this->claim($report);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research/claims");

        $response->assertOk()
            ->assertJsonCount(2, 'data.items');
    }

    public function test_can_update_claim(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $claim = $this->claim($report);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/research/claims/{$claim->id}", [
                'claim' => 'Updated claim text',
                'status' => ResearchClaimStatus::Supported->value,
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Claim updated successfully.')
            ->assertJsonPath('data.claim', 'Updated claim text')
            ->assertJsonPath('data.status', 'supported');

        $this->assertDatabaseHas('research_claims', [
            'id' => $claim->id,
            'claim' => 'Updated claim text',
            'status' => 'supported',
        ]);
    }

    public function test_can_delete_claim(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $claim = $this->claim($report);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/research/claims/{$claim->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Claim deleted successfully.');

        $this->assertDatabaseMissing('research_claims', ['id' => $claim->id]);
    }

    public function test_claim_from_another_project_report_returns_404(): void
    {
        $project = $this->project();
        $otherProject = $this->project();
        $this->report($project);
        $otherReport = $this->report($otherProject);
        $otherClaim = $this->claim($otherReport);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/research/claims/{$otherClaim->id}");

        $response->assertNotFound();

        $this->assertDatabaseHas('research_claims', ['id' => $otherClaim->id]);
    }
}
