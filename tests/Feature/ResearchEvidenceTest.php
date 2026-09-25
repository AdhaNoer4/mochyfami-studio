<?php

namespace Tests\Feature;

use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Source;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResearchEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_claim_can_belong_to_many_sources(): void
    {
        $report = ResearchReport::factory()->create();
        $claim = ResearchClaim::factory()->create(['research_report_id' => $report->id]);
        $sources = Source::factory()->count(3)->create(['research_report_id' => $report->id]);

        $claim->sources()->attach($sources->pluck('id')->all());

        $this->assertCount(3, $claim->sources);
        $this->assertCount(3, $claim->sources()->get());
        $this->assertDatabaseCount('research_claim_sources', 3);
    }

    public function test_source_can_belong_to_many_claims(): void
    {
        $report = ResearchReport::factory()->create();
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claims = ResearchClaim::factory()->count(2)->create(['research_report_id' => $report->id]);

        $source->claims()->attach($claims->pluck('id')->all());

        $this->assertCount(2, $source->claims);
        $this->assertDatabaseCount('research_claim_sources', 2);
    }

    public function test_duplicate_pivot_relationship_is_prevented(): void
    {
        $report = ResearchReport::factory()->create();
        $claim = ResearchClaim::factory()->create(['research_report_id' => $report->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);

        $claim->sources()->attach($source->id);

        $this->expectException(QueryException::class);

        DB::table('research_claim_sources')->insert([
            'research_claim_id' => $claim->id,
            'source_id' => $source->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_pivot_records_have_timestamps(): void
    {
        $report = ResearchReport::factory()->create();
        $claim = ResearchClaim::factory()->create(['research_report_id' => $report->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);

        $claim->sources()->attach($source->id);

        $this->assertDatabaseHas('research_claim_sources', [
            'research_claim_id' => $claim->id,
            'source_id' => $source->id,
        ]);

        $pivot = DB::table('research_claim_sources')
            ->where('research_claim_id', $claim->id)
            ->where('source_id', $source->id)
            ->first();

        $this->assertNotNull($pivot->created_at);
        $this->assertNotNull($pivot->updated_at);
    }

    public function test_deleting_claim_cleans_evidence_relationships(): void
    {
        $report = ResearchReport::factory()->create();
        $claim = ResearchClaim::factory()->create(['research_report_id' => $report->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim->sources()->attach($source->id);

        $claim->delete();

        $this->assertDatabaseMissing('research_claims', ['id' => $claim->id]);
        $this->assertDatabaseMissing('research_claim_sources', [
            'research_claim_id' => $claim->id,
            'source_id' => $source->id,
        ]);
        $this->assertDatabaseHas('sources', ['id' => $source->id]);
    }

    public function test_deleting_source_cleans_evidence_relationships(): void
    {
        $report = ResearchReport::factory()->create();
        $claim = ResearchClaim::factory()->create(['research_report_id' => $report->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim->sources()->attach($source->id);

        $source->delete();

        $this->assertDatabaseMissing('sources', ['id' => $source->id]);
        $this->assertDatabaseMissing('research_claim_sources', [
            'research_claim_id' => $claim->id,
            'source_id' => $source->id,
        ]);
        $this->assertDatabaseHas('research_claims', ['id' => $claim->id]);
    }

    public function test_deleting_project_research_report_cleans_evidence(): void
    {
        $project = ContentProject::factory()->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
        $claim = ResearchClaim::factory()->create(['research_report_id' => $report->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim->sources()->attach($source->id);

        $project->delete();

        $this->assertDatabaseCount('research_claim_sources', 0);
    }
}
