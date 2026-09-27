<?php

namespace Tests\Feature;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Exceptions\ScriptGenerationNotReadyException;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Script;
use App\Models\ScriptVersion;
use App\Models\Source;
use App\Services\AI\Contracts\ScriptGenerationProvider;
use App\Services\AI\DTO\ScriptGenerationRequest;
use App\Services\AI\DTO\ScriptGenerationResponse;
use App\Services\AI\ScriptGenerationProviderRegistry;
use App\Services\Script\ScriptGenerationService;
use App\Services\ScriptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScriptGenerationContextIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scriptService = app(ScriptService::class);
    }

    private ScriptService $scriptService;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Why Cats Purr',
            'hook' => 'Why do cats purr?',
            'body' => "Cats purr to communicate with humans.\n",
            'closing' => 'Now you know.',
            'duration_seconds' => 45,
            'notes' => null,
        ], $overrides);
    }

    private function projectWithMixedReport(): ContentProject
    {
        $project = ContentProject::factory()->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);

        $supported = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'claim' => 'Kucing purr untuk berkomunikasi dengan manusia.',
            'status' => ResearchClaimStatus::Supported,
            'importance' => ResearchClaimImportance::High,
        ]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $supported->sources()->attach($source->id);

        $unverified = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'claim' => 'Kucing bisa bermimpi tentang pemiliknya.',
            'status' => ResearchClaimStatus::Unverified,
            'importance' => ResearchClaimImportance::Low,
        ]);
        $unverified->sources()->attach($source->id);

        $this->scriptService->createScript($project, $this->payload());

        return $project;
    }

    private function installProvider(ScriptGenerationProvider $provider): void
    {
        app()->instance(ScriptGenerationProviderRegistry::class, new ScriptGenerationProviderRegistry([
            $provider->name() => $provider,
        ]));
    }

    public function test_generation_consumes_the_authoritative_context(): void
    {
        $project = $this->projectWithMixedReport();

        $capturing = new class implements ScriptGenerationProvider
        {
            public ?ScriptGenerationRequest $request = null;

            public function name(): string
            {
                return 'capture';
            }

            public function generate(ScriptGenerationRequest $request): ScriptGenerationResponse
            {
                $this->request = $request;

                return new ScriptGenerationResponse(
                    provider: $this->name(),
                    hook: 'Tahukah kamu?',
                    body: "Kucing purr untuk berkomunikasi dengan manusia.\n"
                );
            }
        };

        $this->installProvider($capturing);

        $generationService = app(ScriptGenerationService::class);

        $generationService->generate($project, ['provider' => 'capture', 'topic' => 'Kenapa kucing purr?']);

        $context = $capturing->request->researchContext;

        $this->assertSame('ready_for_script', $context['pipeline']['stage']);
        $this->assertTrue($context['pipeline']['ready_for_script']);
        $this->assertTrue($context['quality']['ready']);
        $this->assertSame(100, $context['quality']['score']);
        $this->assertCount(1, $context['usable_claims']);
        $this->assertSame('Kucing purr untuk berkomunikasi dengan manusia.', $context['usable_claims'][0]['claim']);
        $this->assertCount(1, $context['claims_requiring_verification']);
        $this->assertSame('Kucing bisa bermimpi tentang pemiliknya.', $context['claims_requiring_verification'][0]['claim']);
        $this->assertSame([], $context['contradicted_claims']);
        $this->assertSame([], $context['unsupported_claims']);
        $this->assertCount(1, $context['usable_claims'][0]['sources']);
    }

    public function test_unverified_claims_never_reach_the_usable_set(): void
    {
        $project = $this->projectWithMixedReport();

        $capturing = new class implements ScriptGenerationProvider
        {
            public ?ScriptGenerationRequest $request = null;

            public function name(): string
            {
                return 'capture';
            }

            public function generate(ScriptGenerationRequest $request): ScriptGenerationResponse
            {
                $this->request = $request;

                return new ScriptGenerationResponse(
                    provider: $this->name(),
                    hook: 'Tahukah kamu?',
                    body: "Kucing purr untuk berkomunikasi dengan manusia.\n"
                );
            }
        };

        $this->installProvider($capturing);

        $generationService = app(ScriptGenerationService::class);

        $generationService->generate($project, ['provider' => 'capture', 'topic' => 'Kenapa kucing purr?']);

        $usableClaims = $capturing->request->researchContext['usable_claims'];

        $this->assertNotContains(
            'Kucing bisa bermimpi tentang pemiliknya.',
            array_column($usableClaims, 'claim')
        );
        $this->assertCount(1, $usableClaims);
    }

    public function test_fake_provider_surfaces_a_usable_claim_in_the_generated_body(): void
    {
        Http::preventStrayRequests();

        $project = $this->projectWithMixedReport();

        $result = app(ScriptGenerationService::class)->generate($project, ['topic' => 'Kenapa kucing purr?']);

        $body = $result['generated_version']->body;
        $this->assertStringContainsString('Kucing purr untuk berkomunikasi dengan manusia.', $body);
        $this->assertStringContainsString('Bahan riset terverifikasi:', $body);
        $this->assertStringNotContainsString('Bahan riset terverifikasi: Kucing bisa bermimpi tentang pemiliknya.', $body);
    }

    public function test_contradicted_research_still_blocks_generation(): void
    {
        Http::preventStrayRequests();

        $project = ContentProject::factory()->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'claim' => 'Cats chirp at birds',
            'status' => ResearchClaimStatus::Contradicted,
            'importance' => ResearchClaimImportance::High,
        ]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim->sources()->attach($source->id);
        Script::factory()
            ->has(ScriptVersion::factory(), 'versions')
            ->create(['content_project_id' => $project->id]);

        $this->expectException(ScriptGenerationNotReadyException::class);

        app(ScriptGenerationService::class)->generate($project, ['topic' => 'Kenapa kucing purr?']);
    }
}
