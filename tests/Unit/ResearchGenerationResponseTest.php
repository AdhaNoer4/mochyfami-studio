<?php

namespace Tests\Unit;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Enums\SourceType;
use App\Services\AI\DTO\ResearchGenerationResponse;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ResearchGenerationResponseTest extends TestCase
{
    public function test_normalizes_strings_and_nullables(): void
    {
        $response = new ResearchGenerationResponse(
            provider: ' fake ',
            summary: '  ',
            model: '   ',
        );

        $this->assertSame('fake', $response->provider);
        $this->assertNull($response->summary);
        $this->assertNull($response->model);
        $this->assertSame([], $response->claims);
        $this->assertSame([], $response->sources);
    }

    public function test_empty_raw_metadata_becomes_null(): void
    {
        $response = new ResearchGenerationResponse('fake', null, rawMetadata: []);

        $this->assertNull($response->rawMetadata);
    }

    public function test_claims_are_normalized_and_forced_unverified(): void
    {
        $response = new ResearchGenerationResponse(
            provider: 'fake',
            summary: null,
            claims: [
                ['claim' => '  Klaim kandidat satu.  ', 'importance' => 'high', 'status' => ResearchClaimStatus::Supported],
                ['claim' => 'Klaim kandidat dua.', 'importance' => ResearchClaimImportance::Low],
            ],
        );

        $candidate = $response->claims[0];
        $this->assertSame('Klaim kandidat satu.', $candidate['claim']);
        $this->assertSame(ResearchClaimImportance::High, $candidate['importance']);
        $this->assertSame(ResearchClaimStatus::Unverified, $candidate['status']);
        $this->assertSame(ResearchClaimImportance::Low, $response->claims[1]['importance']);
    }

    public function test_sources_are_normalized_with_default_type(): void
    {
        $response = new ResearchGenerationResponse(
            provider: 'fake',
            summary: null,
            sources: [
                ['title' => '  Sumber kandidat.  ', 'url' => '  https://example.com/abc  ', 'domain' => null],
            ],
        );

        $this->assertSame('Sumber kandidat.', $response->sources[0]['title']);
        $this->assertSame('https://example.com/abc', $response->sources[0]['url']);
        $this->assertNull($response->sources[0]['domain']);
        $this->assertSame(SourceType::Other, $response->sources[0]['source_type']);
    }

    public function test_invalid_importance_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ResearchGenerationResponse(
            provider: 'fake',
            summary: null,
            claims: [['claim' => 'Klaim.', 'importance' => 'proven']],
        );
    }

    public function test_claim_without_text_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ResearchGenerationResponse(
            provider: 'fake',
            summary: null,
            claims: [['importance' => 'high']],
        );
    }

    public function test_source_without_url_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ResearchGenerationResponse(
            provider: 'fake',
            summary: null,
            sources: [['title' => 'Sumber.']],
        );
    }

    public function test_to_array_exposes_normalized_shape(): void
    {
        $response = new ResearchGenerationResponse(
            provider: 'fake',
            summary: 'Ringkasan.',
            model: 'demo',
            claims: [['claim' => 'Klaim.', 'importance' => 'medium']],
            sources: [['title' => 'Sumber.', 'url' => 'https://example.com', 'source_type' => 'news']],
        );

        $this->assertSame([
            'provider' => 'fake',
            'summary' => 'Ringkasan.',
            'model' => 'demo',
            'claims' => [
                ['claim' => 'Klaim.', 'importance' => 'medium', 'status' => 'unverified'],
            ],
            'sources' => [
                ['title' => 'Sumber.', 'url' => 'https://example.com', 'domain' => null, 'source_type' => 'news'],
            ],
        ], $response->toArray());
    }
}
