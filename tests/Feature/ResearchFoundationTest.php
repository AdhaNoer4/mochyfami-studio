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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ResearchFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_research_report_belongs_to_project(): void
    {
        $project = ContentProject::factory()->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);

        $this->assertTrue($report->project->is($project));
    }

    public function test_project_can_access_its_research_report(): void
    {
        $project = ContentProject::factory()->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);

        $this->assertTrue($project->researchReport->is($report));
    }

    public function test_research_report_can_have_claims(): void
    {
        $report = ResearchReport::factory()->create();
        ResearchClaim::factory()->count(3)->create(['research_report_id' => $report->id]);

        $this->assertCount(3, $report->claims);
    }

    public function test_research_report_can_have_sources(): void
    {
        $report = ResearchReport::factory()->create();
        Source::factory()->count(2)->create(['research_report_id' => $report->id]);

        $this->assertCount(2, $report->sources);
    }

    public function test_enum_casts_work_correctly(): void
    {
        $report = ResearchReport::factory()->create([
            'status' => ResearchStatus::Completed,
            'researched_at' => now(),
        ]);
        $claim = ResearchClaim::factory()->create([
            'status' => ResearchClaimStatus::Supported,
            'importance' => ResearchClaimImportance::High,
        ]);
        $source = Source::factory()->create(['source_type' => SourceType::Academic]);

        $this->assertInstanceOf(ResearchStatus::class, $report->status);
        $this->assertSame(ResearchStatus::Completed, $report->status);
        $this->assertSame('completed', $report->getRawOriginal('status'));

        $this->assertInstanceOf(ResearchClaimStatus::class, $claim->status);
        $this->assertSame(ResearchClaimStatus::Supported, $claim->status);
        $this->assertSame(ResearchClaimImportance::High, $claim->importance);

        $this->assertInstanceOf(SourceType::class, $source->source_type);
        $this->assertSame(SourceType::Academic, $source->source_type);
    }

    public function test_duplicate_research_report_for_same_project_is_rejected(): void
    {
        $project = ContentProject::factory()->create();
        ResearchReport::factory()->create(['content_project_id' => $project->id]);

        $this->expectException(QueryException::class);

        ResearchReport::factory()->create(['content_project_id' => $project->id]);
    }

    public function test_deleting_research_report_cascades_to_claims_and_sources(): void
    {
        $report = ResearchReport::factory()->create();
        $claim = ResearchClaim::factory()->create(['research_report_id' => $report->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);

        $report->delete();

        $this->assertDatabaseMissing('research_reports', ['id' => $report->id]);
        $this->assertDatabaseMissing('research_claims', ['id' => $claim->id]);
        $this->assertDatabaseMissing('sources', ['id' => $source->id]);
    }

    public function test_deleting_project_cascades_to_research_report(): void
    {
        $project = ContentProject::factory()->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);

        $project->delete();

        $this->assertDatabaseMissing('research_reports', ['id' => $report->id]);
    }

    public function test_invalid_source_url_cannot_be_saved(): void
    {
        $report = ResearchReport::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        Source::factory()->create([
            'research_report_id' => $report->id,
            'url' => 'not-a-valid-url',
        ]);
    }
}
