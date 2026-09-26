<?php

namespace Tests\Feature;

use App\Enums\ResearchStatus;
use App\Exceptions\SearchProviderException;
use App\Models\ContentProject;
use App\Models\ResearchReport;
use App\Models\User;
use App\Services\Research\Contracts\SearchProvider;
use App\Services\Research\DTO\SearchQuery;
use App\Services\Research\DTO\SearchResponse;
use App\Services\Research\DTO\SearchResult;
use App\Services\Research\FakeSearchProvider;
use App\Services\Research\SearchProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResearchDiscoveryApiTest extends TestCase
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

    private function report(ContentProject $project): ResearchReport
    {
        return ResearchReport::factory()->create(['content_project_id' => $project->id]);
    }

    private function searchResult(string $title, string $url, ?string $snippet = null): SearchResult
    {
        return new SearchResult($title, $url, $snippet);
    }

    private function bindFakeProvider(array $results): void
    {
        $this->app->instance(
            SearchProviderRegistry::class,
            new SearchProviderRegistry(['fake' => new FakeSearchProvider($results)])
        );
    }

    private function bindFailingProvider(): void
    {
        $this->app->instance(SearchProviderRegistry::class, new SearchProviderRegistry([
            'fake' => new class implements SearchProvider
            {
                public function name(): string
                {
                    return 'fake';
                }

                public function search(SearchQuery $query): SearchResponse
                {
                    throw new SearchProviderException(
                        'The search provider is unavailable.',
                        SearchProviderException::UNAVAILABLE
                    );
                }
            },
        ]));
    }

    public function test_owner_can_discover_candidate_sources(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $this->bindFakeProvider([
            $this->searchResult('How Cats Purr', 'https://www.example.com/article/1', 'A snippet about purring.'),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/research/discover", [
                'query' => 'why cats purr',
                'max_results' => 5,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.provider', 'fake')
            ->assertJsonPath('data.query', 'why cats purr')
            ->assertJsonCount(1, 'data.results')
            ->assertJsonPath('data.results.0.title', 'How Cats Purr')
            ->assertJsonPath('data.results.0.domain', 'example.com')
            ->assertJsonPath('data.results.0.url', 'https://www.example.com/article/1')
            ->assertJsonPath('data.results.0.snippet', 'A snippet about purring.');
    }

    public function test_default_provider_is_used_when_omitted(): void
    {
        $project = $this->project();
        $this->report($project);
        $this->bindFakeProvider([$this->searchResult('Cat A', 'https://example.com/a')]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/research/discover", [
                'query' => 'cats',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.provider', 'fake')
            ->assertJsonCount(1, 'data.results');
    }

    public function test_discovery_returns_empty_result_set(): void
    {
        $project = $this->project();
        $this->report($project);
        $this->bindFakeProvider([]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/research/discover", [
                'query' => 'rare topic',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.results', [])
            ->assertJsonCount(0, 'data.results');
    }

    public function test_discovery_requires_authentication(): void
    {
        $project = $this->project();
        $this->report($project);

        $this->postJson("/api/v1/projects/{$project->id}/research/discover", ['query' => 'cats'])
            ->assertStatus(401);
    }

    public function test_discovery_returns_404_when_project_has_no_report(): void
    {
        $project = $this->project();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/research/discover", ['query' => 'cats']);

        $response->assertNotFound()
            ->assertJsonPath('message', 'Research report not found for this project.');
    }

    public function test_discovery_returns_404_for_foreign_project(): void
    {
        $project = $this->project();
        $otherProject = $this->project();
        $this->report($otherProject);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/research/discover", ['query' => 'cats']);

        $response->assertNotFound();
    }

    public function test_discovery_rejects_empty_query(): void
    {
        $project = $this->project();
        $this->report($project);
        $this->bindFakeProvider([]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/research/discover", ['query' => '']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('query');
    }

    public function test_discovery_rejects_invalid_max_results(): void
    {
        $project = $this->project();
        $this->report($project);
        $this->bindFakeProvider([]);

        foreach ([0, 21] as $maxResults) {
            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson("/api/v1/projects/{$project->id}/research/discover", [
                    'query' => 'cats',
                    'max_results' => $maxResults,
                ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors('max_results');
        }
    }

    public function test_discovery_rejects_unknown_provider(): void
    {
        $project = $this->project();
        $this->report($project);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/research/discover", [
                'query' => 'cats',
                'provider' => 'serper',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('provider');
    }

    public function test_discovery_maps_provider_failure_to_controlled_502(): void
    {
        $project = $this->project();
        $this->report($project);
        $this->bindFailingProvider();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/research/discover", ['query' => 'cats']);

        $response->assertStatus(502)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The search provider could not complete the request.')
            ->assertJsonMissingPath('errors.message');
    }

    public function test_discovery_never_mutates_database(): void
    {
        $project = $this->project();
        $report = $this->report($project);
        $this->bindFakeProvider([
            $this->searchResult('Cat A', 'https://example.com/a'),
            $this->searchResult('Cat B', 'https://example.com/b'),
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/research/discover", ['query' => 'cats'])
            ->assertOk();

        $this->assertDatabaseCount('research_reports', 1);
        $this->assertDatabaseCount('research_claims', 0);
        $this->assertDatabaseCount('sources', 0);
        $this->assertDatabaseCount('research_claim_sources', 0);
        $this->assertDatabaseHas('research_reports', [
            'id' => $report->id,
            'status' => ResearchStatus::Pending->value,
        ]);
    }

    public function test_discovery_makes_zero_network_requests(): void
    {
        Http::preventStrayRequests();

        $project = $this->project();
        $this->report($project);
        $this->bindFakeProvider([$this->searchResult('Cat A', 'https://example.com/a')]);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/research/discover", ['query' => 'cats'])
            ->assertOk();
    }
}
