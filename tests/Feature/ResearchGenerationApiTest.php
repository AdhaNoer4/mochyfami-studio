<?php

namespace Tests\Feature;

use App\Enums\AiGenerationStatus;
use App\Enums\ResearchClaimStatus;
use App\Enums\ResearchStatus;
use App\Exceptions\ResearchGenerationProviderException;
use App\Models\AiGeneration;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Source;
use App\Models\User;
use App\Services\AI\Contracts\ResearchGenerationProvider;
use App\Services\AI\DTO\ResearchGenerationRequest;
use App\Services\AI\DTO\ResearchGenerationResponse;
use App\Services\AI\ResearchGenerationProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResearchGenerationApiTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    private function actingAsRandomUser(): static
    {
        $user = User::factory()->create();

        return $this->actingAs($user, 'sanctum');
    }

    private function projectWithReport(): array
    {
        $project = ContentProject::factory()->create();
        $report = ResearchReport::factory()->create([
            'content_project_id' => $project->id,
            'status' => ResearchStatus::Pending,
            'summary' => 'Kurasi manual yang tidak boleh ditimpa.',
        ]);

        return [$project, $report];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'topic' => 'Kenapa kucing suka kardus?',
            'question' => 'Mengapa kucing suka kardus?',
            'max_claims' => 5,
            'provider' => 'fake',
        ], $overrides);
    }

    private function installRegistry(array $providers): void
    {
        app()->instance(ResearchGenerationProviderRegistry::class, new ResearchGenerationProviderRegistry($providers));
    }

    public function test_guest_is_rejected(): void
    {
        [$project] = $this->projectWithReport();

        $this->postJson($this->uri($project), $this->payload())
            ->assertUnauthorized();
    }

    private function uri(ContentProject $project): string
    {
        return "/api/v1/projects/{$project->id}/research/generate";
    }

    public function test_validation_requires_topic(): void
    {
        $this->actingAsRandomUser();
        [$project] = $this->projectWithReport();
        $reportId = ResearchReport::where('content_project_id', $project->id)->value('id');

        $this->postJson($this->uri($project), ['question' => 'Pertanyaan valid.'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['topic']);

        $this->assertSame(0, ResearchClaim::where('research_report_id', $reportId)->count());
        $this->assertSame(0, AiGeneration::where('research_report_id', $reportId)->count());
    }

    public function test_validation_rejects_out_of_range_max_claims(): void
    {
        $this->actingAsRandomUser();
        [$project] = $this->projectWithReport();

        $this->postJson($this->uri($project), $this->payload(['max_claims' => 999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['max_claims']);
    }

    public function test_unknown_provider_is_unprocessable(): void
    {
        $this->actingAsRandomUser();
        [$project] = $this->projectWithReport();

        $this->postJson($this->uri($project), $this->payload(['provider' => 'does-not-exist']))
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'The requested research generation provider is not available.']);
    }

    public function test_missing_report_is_not_found(): void
    {
        $this->actingAsRandomUser();
        $project = ContentProject::factory()->create();

        $this->postJson($this->uri($project), $this->payload())
            ->assertNotFound();
    }

    public function test_provider_failure_is_bad_gateway(): void
    {
        $this->actingAsRandomUser();
        [$project] = $this->projectWithReport();
        $reportId = ResearchReport::where('content_project_id', $project->id)->value('id');

        $failing = new class implements ResearchGenerationProvider
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
        $this->installRegistry(['boom' => $failing]);

        $this->postJson($this->uri($project), $this->payload(['provider' => 'boom']))
            ->assertStatus(502);

        $this->assertSame(0, ResearchClaim::where('research_report_id', $reportId)->count());
        $this->assertSame(AiGenerationStatus::Failed, AiGeneration::where('research_report_id', $reportId)->value('status'));
    }

    public function test_empty_claim_response_is_bad_gateway(): void
    {
        $this->actingAsRandomUser();
        [$project] = $this->projectWithReport();
        $reportId = ResearchReport::where('content_project_id', $project->id)->value('id');

        $empty = new class implements ResearchGenerationProvider
        {
            public function name(): string
            {
                return 'empty';
            }

            public function generate(ResearchGenerationRequest $request): ResearchGenerationResponse
            {
                return new ResearchGenerationResponse(provider: 'empty', summary: null, claims: []);
            }
        };
        $this->installRegistry(['empty' => $empty]);

        $this->postJson($this->uri($project), $this->payload(['provider' => 'empty']))
            ->assertStatus(502);

        $this->assertSame(0, ResearchClaim::where('research_report_id', $reportId)->count());
        $this->assertSame(AiGenerationStatus::Failed, AiGeneration::where('research_report_id', $reportId)->value('status'));
    }

    public function test_happy_path_returns_generated_context(): void
    {
        $this->actingAsRandomUser();
        [$project, $report] = $this->projectWithReport();

        $response = $this->postJson($this->uri($project), $this->payload())
            ->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'report',
                    'summary',
                    'generated_claims',
                    'generated_sources',
                    'generation' => [
                        'provider',
                        'model',
                        'prompt_profile',
                        'prompt_version',
                        'status',
                        'research_report_id',
                    ],
                    'quality' => ['ready', 'score'],
                    'warning',
                ],
            ]);

        $json = $response->json('data');

        $this->assertSame($report->id, $json['generation']['research_report_id']);
        $this->assertSame('fake', $json['generation']['provider']);
        $this->assertSame('mochyfami_research', $json['generation']['prompt_profile']);
        $this->assertSame('completed', $json['generation']['status']);
        $this->assertCount(4, $json['generated_claims']);
        $this->assertSame([], $json['generated_sources']);
        $this->assertNotNull($json['summary']);
        $this->assertStringContainsString('verified', $json['warning']);

        foreach ($json['generated_claims'] as $claim) {
            $this->assertSame(ResearchClaimStatus::Unverified->value, $claim['status']);
            $this->assertNotSame('', $claim['claim']);
        }
    }

    public function test_success_preserves_research_status_and_manual_summary(): void
    {
        $this->actingAsRandomUser();
        [$project, $report] = $this->projectWithReport();

        $this->postJson($this->uri($project), $this->payload())->assertCreated();

        $fresh = $report->fresh();
        $this->assertSame(ResearchStatus::Pending, $fresh->status);
        $this->assertSame('Kurasi manual yang tidak boleh ditimpa.', $fresh->summary);
        $this->assertNull($fresh->researched_at);
    }

    public function test_http_requests_are_prevented(): void
    {
        Http::preventStrayRequests();
        $this->actingAsRandomUser();
        [$project] = $this->projectWithReport();

        $this->postJson($this->uri($project), $this->payload())->assertCreated();
    }

    public function test_request_is_bounded(): void
    {
        $this->actingAsRandomUser();
        [$project] = $this->projectWithReport();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->postJson($this->uri($project), $this->payload())->assertCreated();

        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(24, $queries);
    }

    public function test_unrelated_users_can_generate(): void
    {
        $this->actingAsRandomUser();
        [$project] = $this->projectWithReport();

        $this->postJson($this->uri($project), $this->payload())->assertCreated();
    }

    public function test_candidate_claims_have_no_evidence_attached(): void
    {
        $this->actingAsRandomUser();
        [$project, $report] = $this->projectWithReport();

        $this->postJson($this->uri($project), $this->payload())->assertCreated();

        $this->assertSame(4, ResearchClaim::where('research_report_id', $report->id)->count());
        $this->assertSame([], DB::table('research_claim_sources')->get()->all());
        $this->assertSame(0, Source::where('research_report_id', $report->id)->count());
    }

    public function test_generated_claims_are_only_intended_mutations(): void
    {
        $this->actingAsRandomUser();
        [$project, $report] = $this->projectWithReport();

        $this->postJson($this->uri($project), $this->payload())->assertCreated();

        $this->assertSame(1, AiGeneration::where('research_report_id', $report->id)->where('status', AiGenerationStatus::Completed)->count());
        $this->assertSame(4, ResearchClaim::where('research_report_id', $report->id)->count());
    }
}
