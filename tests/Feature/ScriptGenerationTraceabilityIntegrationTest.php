<?php

namespace Tests\Feature;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Enums\ScriptStatus;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Script;
use App\Models\ScriptVersion;
use App\Models\Source;
use App\Services\Script\ScriptGenerationService;
use App\Services\Script\ScriptResearchTraceabilityService;
use App\Services\ScriptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScriptGenerationTraceabilityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function generationService(): ScriptGenerationService
    {
        return app(ScriptGenerationService::class);
    }

    private function scriptService(): ScriptService
    {
        return app(ScriptService::class);
    }

    private function traceabilityService(): ScriptResearchTraceabilityService
    {
        return app(ScriptResearchTraceabilityService::class);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Why Cats Purr',
            'hook' => 'Why do cats purr?',
            'body' => "Cats purr to communicate with humans, and it soothes them.\n",
            'closing' => 'Now you know why cats purr.',
            'duration_seconds' => 45,
            'notes' => null,
        ], $overrides);
    }

    /**
     * @return array{0: ContentProject, 1: Script, 2: ScriptVersion, 3: ResearchClaim}
     */
    private function readyProjectWithScript(): array
    {
        $project = ContentProject::factory()->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'claim' => 'Cats purr to communicate with humans',
            'status' => ResearchClaimStatus::Supported,
            'importance' => ResearchClaimImportance::High,
        ]);
        $claim->sources()->attach($source->id);

        $script = $this->scriptService()->createScript($project, $this->payload());
        $version = $script->currentVersion;

        $this->traceabilityService()->attachClaim($project, $version, $claim);

        return [$project, $script, $version, $claim];
    }

    public function test_generation_never_auto_maps_claims_to_the_new_version(): void
    {
        [$project, $script, $version] = $this->readyProjectWithScript();

        $result = $this->generationService()->generate($project, ['topic' => 'Kenapa kucing suka kardus?']);

        $generated = $result['generated_version'];

        $this->assertSame(2, $generated->version);
        $this->assertSame(1, $script->fresh()->versions()->count() - 1, 'A new version should exist.');
        $this->assertCount(0, $generated->researchClaims()->get(), 'AI generation must never auto-map claims.');
        $this->assertTrue($generated->researchClaims()->get()->isEmpty());
    }

    public function test_generation_isolates_mappings_from_previous_version(): void
    {
        [$project, , $version, $claim] = $this->readyProjectWithScript();

        $result = $this->generationService()->generate($project, ['topic' => 'Kenapa kucing suka kardus?']);
        $generated = $result['generated_version'];

        $this->assertCount(1, $version->researchClaims()->get());
        $this->assertCount(0, $generated->researchClaims()->get());

        $this->assertDatabaseHas('script_version_research_claim', [
            'script_version_id' => $version->id,
            'research_claim_id' => $claim->id,
        ]);
        $this->assertDatabaseCount('script_version_research_claim', 1);
    }

    public function test_creating_a_version_does_not_copy_mappings(): void
    {
        [$project, $script, $version, $claim] = $this->readyProjectWithScript();

        $updated = $this->scriptService()->createVersion($project, $this->payload());
        $second = $updated->currentVersion;

        $this->assertSame(2, $second->version);
        $this->assertSame($second->id, $updated->current_version_id);
        $this->assertCount(1, $version->fresh()->researchClaims()->get());
        $this->assertCount(0, $second->researchClaims()->get());

        $this->assertDatabaseHas('script_version_research_claim', [
            'script_version_id' => $version->id,
            'research_claim_id' => $claim->id,
        ]);
        $this->assertDatabaseCount('script_version_research_claim', 1);
    }

    public function test_status_transition_does_not_mutate_mappings(): void
    {
        [$project, $script, $version, $claim] = $this->readyProjectWithScript();

        $this->scriptService()->transitionStatus($project, ScriptStatus::Review);
        $this->generationService()->generate($project, ['topic' => 'Kenapa kucing suka kardus?']);

        $this->assertSame(ScriptStatus::Review, $script->fresh()->status);
        $this->assertCount(1, $version->researchClaims()->get(), 'Reviewing or generating must not change mappings.');
        $this->assertDatabaseHas('script_version_research_claim', [
            'script_version_id' => $version->id,
            'research_claim_id' => $claim->id,
        ]);
    }

    public function test_generation_does_not_change_research_claim_status(): void
    {
        [$project, , , $claim] = $this->readyProjectWithScript();

        $this->generationService()->generate($project, ['topic' => 'Kenapa kucing suka kardus?']);

        $this->assertSame(ResearchClaimStatus::Supported, $claim->fresh()->status);
    }

    public function test_generation_succeeds_with_mapped_claims(): void
    {
        [$project, , $version, $claim] = $this->readyProjectWithScript();

        $result = $this->generationService()->generate($project, ['topic' => 'Kenapa kucing suka kardus?']);

        $this->assertSame(2, $result['generated_version']->version);
        $this->assertCount(1, $version->fresh()->researchClaims()->get(), 'Mappings must survive generation.');
        $this->assertDatabaseHas('script_version_research_claim', [
            'script_version_id' => $version->id,
            'research_claim_id' => $claim->id,
        ]);
    }

    public function test_traceability_reads_do_not_mutate_script_or_claims(): void
    {
        [$project, $script, $version, $claim] = $this->readyProjectWithScript();

        $mappings = $this->traceabilityService()->listMappings($project, $version);
        $this->traceabilityService()->summary($version);

        $this->assertCount(1, $mappings);
        $this->assertSame(ScriptStatus::Draft, $script->fresh()->status);
        $this->assertSame($claim->fresh()->status, ResearchClaimStatus::Supported);
        $this->assertSame(ResearchClaimImportance::High, $claim->fresh()->importance);
    }
}
