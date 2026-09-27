<?php

namespace Tests\Feature;

use App\Enums\AiGenerationStatus;
use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Enums\ResearchStatus;
use App\Exceptions\ResearchGenerationProviderException;
use App\Models\AiGeneration;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Source;
use App\Services\AI\Contracts\ResearchGenerationProvider;
use App\Services\AI\DTO\ResearchGenerationRequest;
use App\Services\AI\DTO\ResearchGenerationResponse;
use App\Services\AI\Prompt\MochyFamiResearchProfile;
use App\Services\AI\ResearchGenerationProviderRegistry;
use App\Services\ResearchGenerationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResearchGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function generationService(): ResearchGenerationService
    {
        return app(ResearchGenerationService::class);
    }

    private function projectWithReport(ResearchStatus $status = ResearchStatus::Pending): array
    {
        $project = ContentProject::factory()->create();
        $report = ResearchReport::factory()->create([
            'content_project_id' => $project->id,
            'status' => $status,
            'summary' => 'Kurasi manual yang tidak boleh ditimpa.',
        ]);

        return [$project, $report];
    }

    private function installRegistry(array $providers): ResearchGenerationProviderRegistry
    {
        $registry = new ResearchGenerationProviderRegistry($providers);

        app()->instance(ResearchGenerationProviderRegistry::class, $registry);

        return $registry;
    }

    private function failingProvider(): ResearchGenerationProvider
    {
        return new class implements ResearchGenerationProvider
        {
            public function name(): string
            {
                return 'boom';
            }

            public function generate(ResearchGenerationRequest $request): ResearchGenerationResponse
            {
                throw new ResearchGenerationProviderException(
                    'Provider exploded.',
                    ResearchGenerationProviderException::UNAVAILABLE
                );
            }
        };
    }

    public function test_missing_report_throws_not_found(): void
    {
        $project = ContentProject::factory()->create();

        $this->expectException(ModelNotFoundException::class);
        $this->generationService()->generate($project, [
            'topic' => 'Kenapa kucing suka kardus?',
            'question' => 'Mengapa kucing suka kardus?',
        ]);
    }

    public function test_does_not_require_ready_research(): void
    {
        [$project] = $this->projectWithReport();

        $result = $this->generationService()->generate($project, [
            'topic' => 'Kenapa kucing suka kardus?',
            'question' => 'Mengapa kucing suka kardus?',
        ]);

        $this->assertCount(4, $result['generated_claims']);
        $this->assertTrue($result['report'] instanceof ResearchReport);
    }

    public function test_persists_unverified_claims(): void
    {
        [$project, $report] = $this->projectWithReport();

        $result = $this->generationService()->generate($project, [
            'topic' => 'Kenapa kucing suka kardus?',
            'question' => 'Mengapa kucing suka kardus?',
        ]);

        $this->assertCount(4, $report->fresh()->claims);
        $this->assertSame(4, ResearchClaim::where('research_report_id', $report->id)->count());

        foreach ($result['generated_claims'] as $claim) {
            $this->assertSame(ResearchClaimStatus::Unverified, $claim->status);
            $this->assertSame($report->id, $claim->research_report_id);
            $this->assertCount(0, $claim->sources);
        }
    }

    public function test_persists_sources_as_candidates(): void
    {
        [$project, $report] = $this->projectWithReport();

        $provider = new class implements ResearchGenerationProvider
        {
            public function name(): string
            {
                return 'with_sources';
            }

            public function generate(ResearchGenerationRequest $request): ResearchGenerationResponse
            {
                return new ResearchGenerationResponse(
                    provider: 'with_sources',
                    summary: null,
                    claims: [['claim' => 'Kandidat klaim.', 'importance' => 'high']],
                    sources: [
                        ['title' => 'Kandidat sumber.', 'url' => 'https://example.com/artikel', 'domain' => null],
                    ],
                );
            }
        };
        $this->installRegistry(['with_sources' => $provider]);

        $result = $this->generationService()->generate($project, [
            'topic' => 'Topic',
            'question' => 'Question',
            'provider' => 'with_sources',
        ]);

        $this->assertCount(1, $result['generated_sources']);
        $source = $result['generated_sources']->first();
        $this->assertSame('example.com', $source->domain);
        $this->assertSame($report->id, $source->research_report_id);
        $this->assertCount(0, $source->claims, 'Candidate sources must not be attached to claims automatically.');
    }

    public function test_preserves_existing_claims_and_sources(): void
    {
        [$project, $report] = $this->projectWithReport();
        $existingClaim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'claim' => 'Klaim lama.',
            'status' => ResearchClaimStatus::Supported,
            'importance' => ResearchClaimImportance::High,
        ]);
        $existingSource = Source::factory()->create([
            'research_report_id' => $report->id,
            'title' => 'Sumber lama.',
            'url' => 'https://old.example.com',
        ]);
        $existingClaim->sources()->attach($existingSource->id);

        $this->generationService()->generate($project, [
            'topic' => 'Topic baru',
            'question' => 'Pertanyaan baru',
        ]);

        $this->assertSame('Klaim lama.', $existingClaim->fresh()->claim);
        $this->assertSame(ResearchClaimStatus::Supported, $existingClaim->fresh()->status);
        $this->assertSame('Sumber lama.', $existingSource->fresh()->title);
        $this->assertCount(1, $existingClaim->fresh()->sources);
        $this->assertSame(1, ResearchClaim::where('research_report_id', $report->id)->where('claim', 'Klaim lama.')->count());
    }

    public function test_never_mutates_research_status_or_summary(): void
    {
        [$project, $report] = $this->projectWithReport();
        $this->assertSame(ResearchStatus::Pending, $report->status);

        $result = $this->generationService()->generate($project, [
            'topic' => 'Kenapa kucing suka kardus?',
            'question' => 'Mengapa kucing suka kardus?',
        ]);

        $this->assertSame(ResearchStatus::Pending, $result['report']->status);
        $this->assertSame('Kurasi manual yang tidak boleh ditimpa.', $result['report']->summary);
        $this->assertNull($result['report']->researched_at);
    }

    public function test_quality_is_re_evaluated_and_remains_authoritative(): void
    {
        [$project, $report] = $this->projectWithReport();

        $result = $this->generationService()->generate($project, [
            'topic' => 'Kenapa kucing suka kardus?',
            'question' => 'Mengapa kucing suka kardus?',
        ]);

        $this->assertArrayHasKey('ready', $result['quality']);
        $this->assertArrayHasKey('score', $result['quality']);
        $this->assertSame(ResearchStatus::Pending, $report->fresh()->status, 'Generation must not auto-complete research.');
    }

    public function test_provider_failure_persists_nothing_and_audits_failed(): void
    {
        [$project, $report] = $this->projectWithReport();
        $this->installRegistry(['boom' => $this->failingProvider()]);

        try {
            $this->generationService()->generate($project, [
                'topic' => 'Topic',
                'question' => 'Question',
                'provider' => 'boom',
            ]);
            $this->fail('Expected ResearchGenerationProviderException.');
        } catch (ResearchGenerationProviderException $e) {
            $this->assertSame(ResearchGenerationProviderException::UNAVAILABLE, $e->getCode());
        }

        $this->assertSame(0, ResearchClaim::where('research_report_id', $report->id)->count());
        $this->assertSame(0, Source::where('research_report_id', $report->id)->count());

        $audit = AiGeneration::where('research_report_id', $report->id)->first();
        $this->assertNotNull($audit);
        $this->assertSame('boom', $audit->provider);
        $this->assertSame(AiGenerationStatus::Failed, $audit->status);
        $this->assertNull($audit->script_id);
    }

    public function test_too_many_claims_is_rejected_and_rolled_back(): void
    {
        [$project, $report] = $this->projectWithReport();

        $provider = new class implements ResearchGenerationProvider
        {
            public function name(): string
            {
                return 'flood';
            }

            public function generate(ResearchGenerationRequest $request): ResearchGenerationResponse
            {
                return new ResearchGenerationResponse(
                    provider: 'flood',
                    summary: null,
                    claims: array_fill(0, 8, ['claim' => 'Klaim kandidat.', 'importance' => 'medium']),
                );
            }
        };
        $this->installRegistry(['flood' => $provider]);

        try {
            $this->generationService()->generate($project, [
                'topic' => 'Topic',
                'question' => 'Question',
                'provider' => 'flood',
                'max_claims' => 5,
            ]);
            $this->fail('Expected ResearchGenerationProviderException.');
        } catch (ResearchGenerationProviderException $e) {
            $this->assertSame(ResearchGenerationProviderException::INVALID_RESPONSE, $e->getCode());
        }

        $this->assertSame(0, ResearchClaim::where('research_report_id', $report->id)->count());
        $failedAudit = AiGeneration::where('research_report_id', $report->id)->first();
        $this->assertSame(AiGenerationStatus::Failed, $failedAudit?->status);
    }

    public function test_empty_claim_text_is_rejected(): void
    {
        [$project, $report] = $this->projectWithReport();

        $provider = new class implements ResearchGenerationProvider
        {
            public function name(): string
            {
                return 'blank';
            }

            public function generate(ResearchGenerationRequest $request): ResearchGenerationResponse
            {
                return new ResearchGenerationResponse(
                    provider: 'blank',
                    summary: null,
                    claims: [['claim' => '   ', 'importance' => 'medium']],
                );
            }
        };
        $this->installRegistry(['blank' => $provider]);

        try {
            $this->generationService()->generate($project, [
                'topic' => 'Topic',
                'question' => 'Question',
                'provider' => 'blank',
            ]);
            $this->fail('Expected ResearchGenerationProviderException.');
        } catch (ResearchGenerationProviderException $e) {
            $this->assertSame(ResearchGenerationProviderException::INVALID_RESPONSE, $e->getCode());
        }

        $this->assertSame(0, ResearchClaim::where('research_report_id', $report->id)->count());
        $failedAudit = AiGeneration::where('research_report_id', $report->id)->first();
        $this->assertSame(AiGenerationStatus::Failed, $failedAudit?->status);
    }

    public function test_invalid_source_url_is_rejected_and_rolled_back(): void
    {
        [$project, $report] = $this->projectWithReport();

        $provider = new class implements ResearchGenerationProvider
        {
            public function name(): string
            {
                return 'bad_url';
            }

            public function generate(ResearchGenerationRequest $request): ResearchGenerationResponse
            {
                return new ResearchGenerationResponse(
                    provider: 'bad_url',
                    summary: null,
                    claims: [['claim' => 'Kandidat klaim.', 'importance' => 'high']],
                    sources: [
                        ['title' => 'Sumber rusak.', 'url' => 'not-a-valid-url', 'domain' => null],
                    ],
                );
            }
        };
        $this->installRegistry(['bad_url' => $provider]);

        try {
            $this->generationService()->generate($project, [
                'topic' => 'Topic',
                'question' => 'Question',
                'provider' => 'bad_url',
            ]);
            $this->fail('Expected ResearchGenerationProviderException.');
        } catch (ResearchGenerationProviderException $e) {
            $this->assertSame(ResearchGenerationProviderException::INVALID_RESPONSE, $e->getCode());
        }

        $this->assertSame(0, ResearchClaim::where('research_report_id', $report->id)->count());
        $this->assertSame(0, Source::where('research_report_id', $report->id)->count());
        $failedAudit = AiGeneration::where('research_report_id', $report->id)->first();
        $this->assertSame(AiGenerationStatus::Failed, $failedAudit?->status);
    }

    public function test_successful_generation_is_audited_and_linked(): void
    {
        [$project, $report] = $this->projectWithReport();

        $result = $this->generationService()->generate($project, [
            'topic' => 'Kenapa kucing suka kardus?',
            'question' => 'Mengapa kucing suka kardus?',
        ]);

        $audit = AiGeneration::where('research_report_id', $report->id)->first();
        $this->assertNotNull($audit);
        $this->assertNull($audit->script_id);
        $this->assertSame('fake', $audit->provider);
        $this->assertSame(MochyFamiResearchProfile::PROFILE, $audit->prompt_profile);
        $this->assertSame(MochyFamiResearchProfile::VERSION, $audit->prompt_version);
        $this->assertSame(AiGenerationStatus::Completed, $audit->status);

        $this->assertSame('fake', $result['generation']['provider']);
        $this->assertSame('completed', $result['generation']['status']);
        $this->assertSame($report->id, $result['generation']['research_report_id']);
    }

    public function test_generation_is_deterministic(): void
    {
        [$project] = $this->projectWithReport();

        $first = $this->generationService()->generate($project, [
            'topic' => 'Kenapa kucing suka kardus?',
            'question' => 'Mengapa kucing suka kardus?',
        ]);
        $second = $this->generationService()->generate($project, [
            'topic' => 'Kenapa kucing suka kardus?',
            'question' => 'Mengapa kucing suka kardus?',
        ]);

        $this->assertSame(
            $first['generated_claims']->pluck('claim')->all(),
            $second['generated_claims']->pluck('claim')->all()
        );
    }

    public function test_generation_makes_no_external_http_requests(): void
    {
        Http::preventStrayRequests();
        [$project] = $this->projectWithReport();

        $result = $this->generationService()->generate($project, [
            'topic' => 'Kenapa kucing suka kardus?',
            'question' => 'Mengapa kucing suka kardus?',
        ]);

        $this->assertCount(4, $result['generated_claims']);
    }

    public function test_generation_uses_bounded_query_count(): void
    {
        [$project] = $this->projectWithReport();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->generationService()->generate($project, [
            'topic' => 'Kenapa kucing suka kardus?',
            'question' => 'Mengapa kucing suka kardus?',
        ]);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(18, $queryCount);
    }
}
