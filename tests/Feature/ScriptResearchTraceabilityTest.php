<?php

namespace Tests\Feature;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Exceptions\DuplicateScriptResearchClaimException;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Script;
use App\Models\ScriptVersion;
use App\Models\Source;
use App\Services\Script\ScriptResearchTraceabilityService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScriptResearchTraceabilityTest extends TestCase
{
    use RefreshDatabase;

    private function traceabilityService(): ScriptResearchTraceabilityService
    {
        return app(ScriptResearchTraceabilityService::class);
    }

    /**
     * @return array{0: ContentProject, 1: Script, 2: ScriptVersion, 3: ResearchReport, 4: ResearchClaim, 5: Source}
     */
    private function projectWithScriptAndClaim(): array
    {
        $project = ContentProject::factory()->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
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

        return [$project, $script, $version, $report, $claim, $source];
    }

    public function test_mapping_can_be_created(): void
    {
        [$project, , $version, , $claim] = $this->projectWithScriptAndClaim();

        $this->traceabilityService()->attachClaim($project, $version, $claim);

        $this->assertDatabaseCount('script_version_research_claim', 1);
        $this->assertDatabaseHas('script_version_research_claim', [
            'script_version_id' => $version->id,
            'research_claim_id' => $claim->id,
        ]);
        $this->assertTrue($version->researchClaims()->whereKey($claim->id)->exists());
    }

    public function test_pivot_records_have_timestamps(): void
    {
        [$project, , $version, , $claim] = $this->projectWithScriptAndClaim();

        $this->traceabilityService()->attachClaim($project, $version, $claim);

        $pivot = DB::table('script_version_research_claim')
            ->where('script_version_id', $version->id)
            ->where('research_claim_id', $claim->id)
            ->first();

        $this->assertNotNull($pivot->created_at);
        $this->assertNotNull($pivot->updated_at);
    }

    public function test_duplicate_mapping_is_prevented_by_service(): void
    {
        [$project, , $version, , $claim] = $this->projectWithScriptAndClaim();

        $this->traceabilityService()->attachClaim($project, $version, $claim);

        $this->expectException(DuplicateScriptResearchClaimException::class);

        $this->traceabilityService()->attachClaim($project, $version, $claim);
    }

    public function test_duplicate_mapping_is_prevented_by_unique_constraint(): void
    {
        [$project, , $version, , $claim] = $this->projectWithScriptAndClaim();

        $this->traceabilityService()->attachClaim($project, $version, $claim);

        $this->expectException(QueryException::class);

        DB::table('script_version_research_claim')->insert([
            'script_version_id' => $version->id,
            'research_claim_id' => $claim->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_relationships_work_in_both_directions(): void
    {
        [$project, , $version, , $claim] = $this->projectWithScriptAndClaim();

        $this->traceabilityService()->attachClaim($project, $version, $claim);

        $this->assertCount(1, $version->researchClaims()->get());
        $this->assertSame($claim->id, $version->researchClaims()->first()->id);
        $this->assertTrue($claim->scriptVersions()->whereKey($version->id)->exists());
    }

    public function test_list_mappings_is_deterministic_and_eager_loads_sources(): void
    {
        [$project, , $version, $report, $first, $source] = $this->projectWithScriptAndClaim();
        $second = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'status' => ResearchClaimStatus::Supported,
            'importance' => ResearchClaimImportance::Medium,
        ]);
        $second->sources()->attach($source->id);

        $this->traceabilityService()->attachClaim($project, $version, $second);
        $this->traceabilityService()->attachClaim($project, $version, $first);

        $mappings = $this->traceabilityService()->listMappings($project, $version);

        $this->assertCount(2, $mappings);
        $this->assertSame([$first->id, $second->id], $mappings->pluck('id')->all());
        $this->assertTrue($mappings->every(fn ($claim) => $claim->relationLoaded('sources')));
        $this->assertCount(1, $mappings->first()->sources);
    }

    public function test_attach_validates_version_belongs_to_project_script(): void
    {
        $project = ContentProject::factory()->create();
        $otherScript = Script::factory()
            ->has(ScriptVersion::factory(), 'versions')
            ->create(['content_project_id' => $project->id]);
        $foreignVersion = $otherScript->versions()->orderBy('version')->first();

        [$project2, , , $report] = $this->projectWithScriptAndClaim();
        $foreignClaim = ResearchClaim::factory()->create(['research_report_id' => $report->id]);

        $this->expectException(ModelNotFoundException::class);

        $this->traceabilityService()->attachClaim($project, $foreignVersion, $foreignClaim);
    }

    public function test_attach_rejects_claim_from_another_reports_project(): void
    {
        [$project, , $version] = $this->projectWithScriptAndClaim();

        $otherProject = ContentProject::factory()->create();
        $otherReport = ResearchReport::factory()->create(['content_project_id' => $otherProject->id]);
        $otherClaim = ResearchClaim::factory()->create(['research_report_id' => $otherReport->id]);

        $this->expectException(ModelNotFoundException::class);

        $this->traceabilityService()->attachClaim($project, $version, $otherClaim);
    }

    public function test_attach_rejects_when_project_has_no_script(): void
    {
        $project = ContentProject::factory()->create();
        $version = ScriptVersion::factory()->create();

        $claim = ResearchClaim::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        $this->traceabilityService()->attachClaim($project, $version, $claim);
    }

    public function test_detach_removes_only_the_mapping(): void
    {
        [$project, , $version, $report, $claim] = $this->projectWithScriptAndClaim();

        $this->traceabilityService()->attachClaim($project, $version, $claim);
        $this->traceabilityService()->detachClaim($project, $version, $claim);

        $this->assertDatabaseCount('script_version_research_claim', 0);
        $this->assertDatabaseHas('research_claims', ['id' => $claim->id]);
        $this->assertDatabaseHas('research_reports', ['id' => $report->id]);
    }

    public function test_summary_is_deterministic(): void
    {
        [$project, , $version, $report, $supported] = $this->projectWithScriptAndClaim();
        $source = Source::factory()->create(['research_report_id' => $report->id]);

        $withEvidence = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'status' => ResearchClaimStatus::Supported,
            'importance' => ResearchClaimImportance::Low,
        ]);
        $withEvidence->sources()->attach($source->id);

        $unverified = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'status' => ResearchClaimStatus::Unverified,
            'importance' => ResearchClaimImportance::Low,
        ]);

        $contradicted = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'status' => ResearchClaimStatus::Contradicted,
            'importance' => ResearchClaimImportance::Low,
        ]);

        foreach ([$supported, $withEvidence, $unverified, $contradicted] as $claim) {
            $this->traceabilityService()->attachClaim($project, $version, $claim);
        }

        $summary = $this->traceabilityService()->summary($version);

        $this->assertSame(4, $summary['total_claims']);
        $this->assertSame(2, $summary['supported_claims']);
        $this->assertSame(1, $summary['unverified_claims']);
        $this->assertSame(0, $summary['uncertain_claims']);
        $this->assertSame(1, $summary['contradicted_claims']);
        $this->assertSame(2, $summary['claims_with_evidence']);
        $this->assertSame(2, $summary['claims_without_evidence']);
        $this->assertFalse($summary['traceability_complete']);
        $this->assertNotSame([], $summary['warnings']);
    }

    public function test_summary_with_empty_mappings_is_incomplete(): void
    {
        [, , $version] = $this->projectWithScriptAndClaim();

        $summary = $this->traceabilityService()->summary($version);

        $this->assertSame(0, $summary['total_claims']);
        $this->assertFalse($summary['traceability_complete']);
        $this->assertStringContainsString('No research claims are mapped', $summary['warnings'][0]);
    }

    public function test_summary_is_complete_when_all_mapped_claims_are_supported(): void
    {
        [$project, , $version, $report, $supported] = $this->projectWithScriptAndClaim();
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $supported->sources()->attach($source->id);

        $second = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'status' => ResearchClaimStatus::Supported,
            'importance' => ResearchClaimImportance::Low,
        ]);
        $second->sources()->attach($source->id);

        $this->traceabilityService()->attachClaim($project, $version, $supported);
        $this->traceabilityService()->attachClaim($project, $version, $second);

        $this->assertTrue($this->traceabilityService()->summary($version)['traceability_complete']);
    }

    public function test_deleting_claim_cleans_mapping(): void
    {
        [$project, , $version, , $claim] = $this->projectWithScriptAndClaim();

        $this->traceabilityService()->attachClaim($project, $version, $claim);
        $claim->delete();

        $this->assertDatabaseMissing('research_claims', ['id' => $claim->id]);
        $this->assertDatabaseCount('script_version_research_claim', 0);
    }

    public function test_deleting_script_version_cleans_mapping(): void
    {
        [$project, , $version, , $claim] = $this->projectWithScriptAndClaim();

        $this->traceabilityService()->attachClaim($project, $version, $claim);
        $version->delete();

        $this->assertDatabaseMissing('script_versions', ['id' => $version->id]);
        $this->assertDatabaseCount('script_version_research_claim', 0);
        $this->assertDatabaseHas('research_claims', ['id' => $claim->id]);
    }

    public function test_deleting_project_research_report_cleans_mapping(): void
    {
        [$project, , $version, , $claim] = $this->projectWithScriptAndClaim();

        $this->traceabilityService()->attachClaim($project, $version, $claim);
        $project->delete();

        $this->assertDatabaseCount('script_version_research_claim', 0);
    }
}
