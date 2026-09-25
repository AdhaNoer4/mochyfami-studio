<?php

namespace Tests\Feature;

use App\Enums\ResearchStatus;
use App\Http\Resources\ResearchSourceResource;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResearchEvidenceApiTest extends TestCase
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

    private function claim(ResearchReport $report): ResearchClaim
    {
        return ResearchClaim::factory()->create(['research_report_id' => $report->id]);
    }

    private function source(ResearchReport $report): Source
    {
        return Source::factory()->create(['research_report_id' => $report->id]);
    }

    public function test_can_attach_source_to_claim(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $claim = $this->claim($report);
        $source = $this->source($report);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(
                "/api/v1/projects/{$project->id}/research/claims/{$claim->id}/sources/{$source->id}"
            );

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Source attached to claim successfully.')
            ->assertJsonCount(1, 'data.sources')
            ->assertJsonPath('data.sources.0.id', $source->id);

        $this->assertDatabaseHas('research_claim_sources', [
            'research_claim_id' => $claim->id,
            'source_id' => $source->id,
        ]);
    }

    public function test_attaching_duplicate_source_returns_409(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $claim = $this->claim($report);
        $source = $this->source($report);
        $claim->sources()->attach($source->id);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(
                "/api/v1/projects/{$project->id}/research/claims/{$claim->id}/sources/{$source->id}"
            );

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This source is already attached to the claim.');

        $this->assertDatabaseCount('research_claim_sources', 1);
    }

    public function test_can_detach_source_from_claim_keeping_both_records(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $claim = $this->claim($report);
        $source = $this->source($report);
        $claim->sources()->attach($source->id);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson(
                "/api/v1/projects/{$project->id}/research/claims/{$claim->id}/sources/{$source->id}"
            );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Source detached from claim successfully.');

        $this->assertDatabaseMissing('research_claim_sources', [
            'research_claim_id' => $claim->id,
            'source_id' => $source->id,
        ]);
        $this->assertDatabaseHas('research_claims', ['id' => $claim->id]);
        $this->assertDatabaseHas('sources', ['id' => $source->id]);
    }

    public function test_can_list_claim_sources(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $claim = $this->claim($report);
        $first = $this->source($report);
        $second = $this->source($report);
        $claim->sources()->attach([$first->id, $second->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research/claims/{$claim->id}/sources");

        $response->assertOk()
            ->assertJsonCount(2, 'data.items');
    }

    public function test_cannot_attach_source_from_another_report(): void
    {
        $project = $this->project();
        $otherProject = $this->project();
        $report = $this->report($project);
        $otherReport = $this->report($otherProject);
        $claim = $this->claim($report);
        $foreignSource = $this->source($otherReport);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(
                "/api/v1/projects/{$project->id}/research/claims/{$claim->id}/sources/{$foreignSource->id}"
            );

        $response->assertNotFound();

        $this->assertDatabaseCount('research_claim_sources', 0);
    }

    public function test_cannot_attach_source_to_claim_from_another_project(): void
    {
        $project = $this->project();
        $otherProject = $this->project();
        $this->report($project);
        $otherReport = $this->report($otherProject);
        $foreignClaim = $this->claim($otherReport);
        $source = $this->source($otherReport);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(
                "/api/v1/projects/{$project->id}/research/claims/{$foreignClaim->id}/sources/{$source->id}"
            );

        $response->assertNotFound();

        $this->assertDatabaseCount('research_claim_sources', 0);
    }

    public function test_attach_requires_authentication(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $claim = $this->claim($report);
        $source = $this->source($report);

        $response = $this->postJson(
            "/api/v1/projects/{$project->id}/research/claims/{$claim->id}/sources/{$source->id}"
        );

        $response->assertStatus(401);
    }

    public function test_attach_returns_404_for_missing_claim(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $source = $this->source($report);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(
                "/api/v1/projects/{$project->id}/research/claims/99999/sources/{$source->id}"
            );

        $response->assertNotFound();
    }

    public function test_attach_returns_404_for_missing_source(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $claim = $this->claim($report);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson(
                "/api/v1/projects/{$project->id}/research/claims/{$claim->id}/sources/99999"
            );

        $response->assertNotFound();
    }

    public function test_claim_returns_sources_when_eager_loaded(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $claim = $this->claim($report);
        $source = $this->source($report);
        $claim->sources()->attach($source->id);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research");

        $response->assertOk()
            ->assertJsonPath('data.claims.0.sources.0.id', $source->id)
            ->assertJsonPath('data.claims.0.sources.0.title', $source->title);
    }

    public function test_claim_list_does_not_force_load_sources(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $claim = $this->claim($report);
        $source = $this->source($report);
        $claim->sources()->attach($source->id);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research/claims");

        $response->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonMissingPath('data.items.0.sources');
    }

    public function test_source_resource_returns_claims_when_eager_loaded(): void
    {
        $report = ResearchReport::factory()->create();
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claims = ResearchClaim::factory()->count(2)->create(['research_report_id' => $report->id]);
        $source->claims()->attach($claims->pluck('id')->all());
        $source->load('claims');

        $payload = (new ResearchSourceResource($source))->toArray(request());

        $this->assertArrayHasKey('claims', $payload);
        $this->assertCount(2, $payload['claims']);
    }

    public function test_research_response_eager_loads_claim_evidence_without_n1(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $sources = Source::factory()->count(3)->create(['research_report_id' => $report->id]);
        $claims = ResearchClaim::factory()->count(3)->create(['research_report_id' => $report->id]);

        foreach ($claims as $index => $claim) {
            $claim->sources()->attach($sources[$index]->id);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/research")
            ->assertOk()
            ->assertJsonCount(3, 'data.claims')
            ->assertJsonCount(3, 'data.sources');

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(7, $queryCount);
    }
}
