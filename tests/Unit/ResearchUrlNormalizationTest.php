<?php

namespace Tests\Unit;

use App\Enums\SourceType;
use App\Services\Research\DTO\SearchResult;
use App\Services\Research\SourceNormalizer;
use App\Services\Research\Support\DomainExtractor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ResearchUrlNormalizationTest extends TestCase
{
    #[DataProvider('domainProvider')]
    public function test_domains_are_normalized_locally(string $url, string $expected): void
    {
        $this->assertSame($expected, DomainExtractor::fromUrl($url));
        $this->assertTrue(DomainExtractor::isHttpUrl($url));
    }

    public static function domainProvider(): array
    {
        return [
            'https path' => ['https://example.com/article', 'example.com'],
            'https www' => ['https://www.example.com/article', 'example.com'],
            'http query string' => ['http://example.com/test?q=1', 'example.com'],
            'subdomain preserved' => ['https://sub.example.com/path', 'sub.example.com'],
            'port stripped' => ['https://example.com:8080/path', 'example.com'],
            'capitalized host' => ['https://WWW.Example.COM/Path', 'example.com'],
            'trailing dot' => ['https://example.com./path', 'example.com'],
        ];
    }

    #[DataProvider('invalidProtocolProvider')]
    public function test_non_http_protocols_are_rejected(string $url): void
    {
        $this->assertNull(DomainExtractor::fromUrl($url));
        $this->assertFalse(DomainExtractor::isHttpUrl($url));
    }

    public static function invalidProtocolProvider(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html;base64,PHNjcmlwdD4='],
            'file' => ['file:///etc/passwd'],
            'no scheme' => ['example.com'],
            'malformed' => ['::::'],
        ];
    }

    public function test_search_result_uses_normalized_domain(): void
    {
        $result = new SearchResult('Title', 'https://www.example.com/article/1');

        $this->assertSame('example.com', $result->domain);
    }

    public function test_search_result_rejects_unsafe_urls(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html,hi', 'file:///tmp/x'] as $url) {
            try {
                new SearchResult('Unsafe', $url);
                $this->fail("Expected InvalidArgumentException for {$url}");
            } catch (InvalidArgumentException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_source_normalizer_builds_source_compatible_data(): void
    {
        $publishedAt = Carbon::parse('2026-02-02T00:00:00Z');
        $result = new SearchResult(
            'How Cats Purr',
            'https://www.example.com/article/how-cats-purr',
            'Snippet',
            $publishedAt,
        );

        $data = SourceNormalizer::forCandidate($result);

        $this->assertSame('How Cats Purr', $data['title']);
        $this->assertSame('https://www.example.com/article/how-cats-purr', $data['url']);
        $this->assertSame('example.com', $data['domain']);
        $this->assertSame(SourceType::Other, $data['source_type']);
        $this->assertTrue($publishedAt->equalTo($data['published_at']));
    }

    public function test_source_normalizer_performs_no_persistence(): void
    {
        $result = new SearchResult('Title', 'https://example.com/x');
        $data = SourceNormalizer::forCandidate($result);

        $this->assertSame('other', $data['source_type']->value);
        $this->assertDatabaseCount('sources', 0);
        $this->assertDatabaseCount('research_reports', 0);
    }

    public function test_no_network_requests_during_normalization(): void
    {
        Http::preventStrayRequests();

        $this->assertSame('example.com', DomainExtractor::fromUrl('https://www.example.com/a?b=1'));
        $this->assertNull(DomainExtractor::fromUrl('javascript:evil'));
    }
}
