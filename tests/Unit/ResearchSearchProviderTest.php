<?php

namespace Tests\Unit;

use App\Exceptions\SearchProviderException;
use App\Models\Source;
use App\Models\User;
use App\Services\Research\Contracts\SearchProvider;
use App\Services\Research\DTO\SearchQuery;
use App\Services\Research\DTO\SearchResponse;
use App\Services\Research\DTO\SearchResult;
use App\Services\Research\FakeSearchProvider;
use App\Services\Research\SearchProviderRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class ResearchSearchProviderTest extends TestCase
{
    public function test_valid_query_is_normalized(): void
    {
        $query = new SearchQuery('  why cats purr  ', 5);

        $this->assertSame('why cats purr', $query->query);
        $this->assertSame(5, $query->maxResults);
    }

    public function test_empty_query_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SearchQuery('');
    }

    public function test_whitespace_only_query_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SearchQuery('   ');
    }

    public function test_overlong_query_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SearchQuery(str_repeat('a', SearchQuery::MAX_QUERY_LENGTH + 1));
    }

    public function test_query_at_maximum_length_is_accepted(): void
    {
        $query = new SearchQuery(str_repeat('a', SearchQuery::MAX_QUERY_LENGTH));

        $this->assertSame(SearchQuery::MAX_QUERY_LENGTH, mb_strlen($query->query));
    }

    public function test_max_results_below_minimum_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SearchQuery('cats', 0);
    }

    public function test_max_results_above_limit_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SearchQuery('cats', SearchQuery::MAX_RESULTS_LIMIT + 1);
    }

    public function test_max_results_boundaries_accepted(): void
    {
        $this->assertSame(1, (new SearchQuery('cats', 1))->maxResults);
        $this->assertSame(SearchQuery::MAX_RESULTS_LIMIT, (new SearchQuery('cats', SearchQuery::MAX_RESULTS_LIMIT))->maxResults);
    }

    public function test_search_result_exposes_normalized_structure(): void
    {
        $publishedAt = Carbon::parse('2026-01-01T00:00:00Z');
        $result = new SearchResult(
            'How Cats Purr',
            'https://www.example.com/article/how-cats-purr',
            'A short snippet about purring.',
            $publishedAt,
        );

        $this->assertSame('How Cats Purr', $result->title);
        $this->assertSame('https://www.example.com/article/how-cats-purr', $result->url);
        $this->assertSame('A short snippet about purring.', $result->snippet);
        $this->assertSame('example.com', $result->domain);
        $this->assertTrue($publishedAt->equalTo($result->publishedAt));

        $array = $result->toArray();
        $this->assertSame($result->title, $array['title']);
        $this->assertSame($result->url, $array['url']);
        $this->assertSame('example.com', $array['domain']);
        $this->assertSame($result->snippet, $array['snippet']);
    }

    public function test_search_result_rejects_non_http_url(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SearchResult('Bad', 'javascript:alert(1)');
    }

    public function test_fake_provider_returns_configured_results(): void
    {
        $result = new SearchResult('Cat Facts', 'https://example.com/cats');
        $provider = new FakeSearchProvider([$result]);

        $response = $provider->search(new SearchQuery('cats', 5));

        $this->assertSame('fake', $provider->name());
        $this->assertCount(1, $response->results);
        $this->assertSame($result, $response->results[0]);
    }

    public function test_fake_provider_respects_max_results(): void
    {
        $results = [];
        for ($i = 1; $i <= 5; $i++) {
            $results[] = new SearchResult("Cat Fact {$i}", "https://example.com/cats/{$i}");
        }

        $response = (new FakeSearchProvider($results))->search(new SearchQuery('cats', 2));

        $this->assertCount(2, $response->results);
        $this->assertSame('Cat Fact 1', $response->results[0]->title);
        $this->assertSame('Cat Fact 2', $response->results[1]->title);
    }

    public function test_fake_provider_makes_zero_network_requests(): void
    {
        Http::preventStrayRequests();

        $response = (new FakeSearchProvider([
            new SearchResult('Cat Facts', 'https://example.com/cats'),
        ]))->search(new SearchQuery('cats', 5));

        $this->assertCount(1, $response->results);
    }

    public function test_search_response_exposes_response_shape(): void
    {
        $query = new SearchQuery('cats', 2);
        $response = new SearchResponse($query, 'fake', [
            new SearchResult('Cat A', 'https://example.com/a'),
            new SearchResult('Cat B', 'https://example.com/b'),
        ]);

        $this->assertSame($query, $response->query);
        $this->assertSame('fake', $response->provider);
        $this->assertCount(2, $response->results);

        $array = $response->toArray();
        $this->assertSame('fake', $array['provider']);
        $this->assertSame('cats', $array['query']);
        $this->assertCount(2, $array['results']);
        $this->assertSame('Cat A', $array['results'][0]['title']);
        $this->assertSame('example.com', $array['results'][0]['domain']);
    }

    public function test_registry_resolves_registered_provider(): void
    {
        $registry = new SearchProviderRegistry([
            'fake' => FakeSearchProvider::class,
        ]);

        $this->assertTrue($registry->has('fake'));
        $this->assertSame(['fake'], $registry->names());
        $this->assertInstanceOf(FakeSearchProvider::class, $registry->resolve('fake'));
    }

    public function test_registry_resolves_configured_default_provider(): void
    {
        $registry = app(SearchProviderRegistry::class);

        $this->assertSame('fake', config('research.search.default_provider'));
        $this->assertInstanceOf(
            SearchProvider::class,
            $registry->resolve(config('research.search.default_provider'))
        );
    }

    public function test_registry_rejects_unknown_provider(): void
    {
        $registry = new SearchProviderRegistry(['fake' => FakeSearchProvider::class]);

        try {
            $registry->resolve('serper');
            $this->fail('Unknown provider should be rejected.');
        } catch (SearchProviderException $e) {
            $this->assertSame(SearchProviderException::UNKNOWN_PROVIDER, $e->getCode());
        }
    }

    public function test_registry_never_resolves_arbitrary_class_names(): void
    {
        $registry = new SearchProviderRegistry(['fake' => FakeSearchProvider::class]);

        $this->expectException(SearchProviderException::class);
        $registry->resolve(User::class);
    }

    public function test_registry_requires_interface_implementation(): void
    {
        $registry = new SearchProviderRegistry([
            'fake' => FakeSearchProvider::class,
            'broken' => Source::class,
        ]);

        try {
            $registry->resolve('broken');
            $this->fail('Misconfigured provider must be rejected.');
        } catch (SearchProviderException $e) {
            $this->assertSame(SearchProviderException::INVALID_RESPONSE, $e->getCode());
        }
    }
}
