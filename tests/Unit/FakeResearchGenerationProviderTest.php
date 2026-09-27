<?php

namespace Tests\Unit;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Services\AI\DTO\ResearchGenerationRequest;
use App\Services\AI\FakeResearchGenerationProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FakeResearchGenerationProviderTest extends TestCase
{
    private function request(int $maxClaims = 5): ResearchGenerationRequest
    {
        return new ResearchGenerationRequest(
            topic: 'Kenapa kucing suka kardus?',
            question: 'Mengapa kucing menyukai kardus?',
            maxClaims: $maxClaims,
        );
    }

    public function test_name(): void
    {
        $this->assertSame('fake', (new FakeResearchGenerationProvider)->name());
    }

    public function test_output_is_deterministic(): void
    {
        $provider = new FakeResearchGenerationProvider;

        $first = $provider->generate($this->request());
        $second = $provider->generate($this->request());

        $this->assertSame($first->toArray(), $second->toArray());
    }

    public function test_never_includes_timestamps_or_urls(): void
    {
        $response = (new FakeResearchGenerationProvider)->generate($this->request());

        $allText = $response->summary.' '.implode(' ', array_column($response->claims, 'claim'));

        $this->assertStringNotContainsString((string) now()->year, $allText);
        $this->assertStringNotContainsString('https://', $allText);
        $this->assertStringNotContainsString('http://', $allText);
        $this->assertSame([], $response->sources);
    }

    public function test_claims_are_unverified_candidates(): void
    {
        $response = (new FakeResearchGenerationProvider)->generate($this->request(maxClaims: 5));

        $this->assertCount(4, $response->claims);

        foreach ($response->claims as $index => $claim) {
            $this->assertSame(ResearchClaimStatus::Unverified, $claim['status']);
            $this->assertContains($claim['importance'], ResearchClaimImportance::cases());
            $this->assertStringContainsString('belum diverifikasi secara manual', $claim['claim']);
            $this->assertNotSame('', $claim['claim']);
        }
    }

    public function test_respects_max_claims(): void
    {
        $provider = new FakeResearchGenerationProvider;

        $this->assertCount(2, $provider->generate($this->request(maxClaims: 2))->claims);

        $this->assertCount(4, $provider->generate($this->request(maxClaims: 25))->claims);
    }

    public function test_output_uses_request_context(): void
    {
        $response = (new FakeResearchGenerationProvider)->generate($this->request());

        $this->assertStringContainsString('Kenapa kucing suka kardus?', $response->summary);
        $this->assertStringContainsString('Mengapa kucing menyukai kardus?', $response->claims[0]['claim']);
    }

    public function test_never_requires_external_http(): void
    {
        Http::preventStrayRequests();

        (new FakeResearchGenerationProvider)->generate($this->request());

        $this->assertTrue(true);
    }
}
