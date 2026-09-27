<?php

namespace Tests\Feature;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Enums\ResearchStatus;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Script;
use App\Models\ScriptVersion;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScriptResearchTraceabilityApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * @return array{0: ContentProject, 1: Script, 2: ScriptVersion, 3: ResearchReport, 4: ResearchClaim}
     */
    private function projectWithScriptAndClaim(): array
    {
        $project = ContentProject::factory()->create();
        $report = ResearchReport::factory()->create([
            'content_project_id' => $project->id,
            'status' => ResearchStatus::Completed,
        ]);
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'claim' => 'Kucing purr untuk berkomunikasi dengan manusia.',
            'status' => ResearchClaimStatus::Supported,
            'importance' => ResearchClaimImportance::High,
        ]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim->sources()->attach($source->id);

        $script = Script::factory()
            ->has(ScriptVersion::factory(), 'versions')
            ->create(['content_project_id' => $project->id]);
        $version = $script->versions()->orderBy('version')->first();

        return [$project, $script, $version, $report, $claim];
    }

    public function test_list_versions_requires_authentication(): void
    {
        [$project, , $version] = $this->projectWithScriptAndClaim();

        $this->getJson("/api/v1/projects/{$project->id}/script/versions/{$version->version}/research-claims")
            ->assertStatus(401);
    }

    public function test_can_list_mappings_with_traceability_summary(): void
    {
        [$project, , $version, , $claim] = $this->projectWithScriptAndClaim();

        $this->actingAs($this->user, 'sanctum')
            ->postJson(
                "/api/v1/projects/{$project->id}/script/versions/{$version->version}/research-claims/{$claim->id}"
            )
            ->assertCreated();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/script/versions/{$version->version}/research-claims")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.claim', $claim->claim)
            ->assertJsonPath('data.items.0.status', $claim->status->value)
            ->assertJsonPath('data.items.0.importance', $claim->importance->value)
            ->assertJsonPath('data.items.0.has_evidence', true)
            ->assertJsonCount(1, 'data.items.0.sources')
            ->assertJsonPath('data.items.0.sources.0.id', $claim->sources()->first()->id)
            ->assertJsonPath('data.traceability.total_claims', 1)
            ->assertJsonPath('data.traceability.supported_claims', 1)
            ->assertJsonPath('data.traceability.claims_with_evidence', 1)
            ->assertJsonPath('data.traceability.traceability_complete', true);
    }

    public function test_can_attach_claim_to_version(): void
    {
        [$project, , $version, , $claim] = $this->projectWithScriptAndClaim();

        $this->actingAs($this->user, 'sanctum')
            ->postJson(
                "/api/v1/projects/{$project->id}/script/versions/{$version->version}/research-claims/{$claim->id}"
            )
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Research claim mapped to script version successfully.')
            ->assertJsonPath('data.id', $claim->id)
            ->assertJsonPath('data.has_evidence', true);

        $this->assertDatabaseHas('script_version_research_claim', [
            'script_version_id' => $version->id,
            'research_claim_id' => $claim->id,
        ]);
    }

    public function test_duplicate_attach_returns_409(): void
    {
        [$project, , $version, , $claim] = $this->projectWithScriptAndClaim();

        $this->actingAs($this->user, 'sanctum')
            ->postJson(
                "/api/v1/projects/{$project->id}/script/versions/{$version->version}/research-claims/{$claim->id}"
            )
            ->assertCreated();

        $this->actingAs($this->user, 'sanctum')
            ->postJson(
                "/api/v1/projects/{$project->id}/script/versions/{$version->version}/research-claims/{$claim->id}"
            )
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This research claim is already mapped to the script version.');

        $this->assertDatabaseCount('script_version_research_claim', 1);
    }

    public function test_can_detach_claim_keeping_both_records(): void
    {
        [$project, , $version, $report, $claim] = $this->projectWithScriptAndClaim();

        $this->actingAs($this->user, 'sanctum')
            ->postJson(
                "/api/v1/projects/{$project->id}/script/versions/{$version->version}/research-claims/{$claim->id}"
            )
            ->assertCreated();

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson(
                "/api/v1/projects/{$project->id}/script/versions/{$version->version}/research-claims/{$claim->id}"
            )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Research claim detached from script version successfully.');

        $this->assertDatabaseMissing('script_version_research_claim', [
            'script_version_id' => $version->id,
            'research_claim_id' => $claim->id,
        ]);
        $this->assertDatabaseHas('script_versions', ['id' => $version->id]);
        $this->assertDatabaseHas('research_claims', ['id' => $claim->id]);
        $this->assertDatabaseHas('research_reports', ['id' => $report->id]);
    }

    public function test_detaching_an_unmapped_claim_is_idempotent(): void
    {
        [$project, , $version, , $claim] = $this->projectWithScriptAndClaim();

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson(
                "/api/v1/projects/{$project->id}/script/versions/{$version->version}/research-claims/{$claim->id}"
            )
            ->assertOk();

        $this->assertDatabaseCount('script_version_research_claim', 0);
    }

    public function test_cannot_attach_claim_from_another_project(): void
    {
        [$project, , $version] = $this->projectWithScriptAndClaim();

        $otherProject = ContentProject::factory()->create();
        $otherReport = ResearchReport::factory()->create(['content_project_id' => $otherProject->id]);
        $foreignClaim = ResearchClaim::factory()->create(['research_report_id' => $otherReport->id]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson(
                "/api/v1/projects/{$project->id}/script/versions/{$version->version}/research-claims/{$foreignClaim->id}"
            )
            ->assertNotFound()
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('script_version_research_claim', 0);
    }

    public function test_cannot_attach_to_version_of_another_project_script(): void
    {
        [$project, , , , $claim] = $this->projectWithScriptAndClaim();

        $otherProject = ContentProject::factory()->create();
        Script::factory()
            ->has(ScriptVersion::factory()->state(['version' => 7]), 'versions')
            ->create(['content_project_id' => $otherProject->id]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson(
                "/api/v1/projects/{$project->id}/script/versions/7/research-claims/{$claim->id}"
            )
            ->assertNotFound();

        $this->assertDatabaseCount('script_version_research_claim', 0);
    }

    public function test_attach_returns_404_for_missing_project_script(): void
    {
        $project = ContentProject::factory()->create();
        $claim = ResearchClaim::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/1/research-claims/{$claim->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Script not found for this project.');
    }

    public function test_attach_returns_404_for_missing_version_number(): void
    {
        [$project, , , , $claim] = $this->projectWithScriptAndClaim();

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/9999/research-claims/{$claim->id}")
            ->assertNotFound();
    }

    public function test_attach_returns_404_for_missing_claim(): void
    {
        [$project, , $version] = $this->projectWithScriptAndClaim();

        $this->actingAs($this->user, 'sanctum')
            ->postJson(
                "/api/v1/projects/{$project->id}/script/versions/{$version->version}/research-claims/99999"
            )
            ->assertNotFound();
    }

    public function test_list_with_no_mappings_returns_empty_items_and_warning(): void
    {
        [$project, , $version] = $this->projectWithScriptAndClaim();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/script/versions/{$version->version}/research-claims")
            ->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.traceability.total_claims', 0)
            ->assertJsonPath('data.traceability.traceability_complete', false)
            ->assertJsonCount(1, 'data.traceability.warnings');
    }

    public function test_detached_mapping_reflects_in_list(): void
    {
        [$project, , $version, , $claim] = $this->projectWithScriptAndClaim();

        $this->actingAs($this->user, 'sanctum')
            ->postJson(
                "/api/v1/projects/{$project->id}/script/versions/{$version->version}/research-claims/{$claim->id}"
            )
            ->assertCreated();

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson(
                "/api/v1/projects/{$project->id}/script/versions/{$version->version}/research-claims/{$claim->id}"
            )
            ->assertOk();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/script/versions/{$version->version}/research-claims")
            ->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.traceability.total_claims', 0);
    }

    public function test_destroy_requires_authentication(): void
    {
        [$project, , $version, , $claim] = $this->projectWithScriptAndClaim();

        $this->deleteJson(
            "/api/v1/projects/{$project->id}/script/versions/{$version->version}/research-claims/{$claim->id}"
        )->assertStatus(401);
    }

    public function test_invalid_version_format_does_not_match_research_claims_route(): void
    {
        [$project] = $this->projectWithScriptAndClaim();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/script/versions/abc/research-claims")
            ->assertNotFound();
    }
}
