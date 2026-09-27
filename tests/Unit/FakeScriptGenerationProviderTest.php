<?php

namespace Tests\Unit;

use App\Services\AI\DTO\ScriptGenerationRequest;
use App\Services\AI\FakeScriptGenerationProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FakeScriptGenerationProviderTest extends TestCase
{
    private function request(array $overrides = []): ScriptGenerationRequest
    {
        $params = [
            'topic' => 'Kenapa kucing suka kardus?',
            'importantClaims' => ['Kucing suka kardus karena terasa aman dan nyaman.'],
        ];

        return new ScriptGenerationRequest(...array_merge($params, $overrides));
    }

    public function test_name(): void
    {
        $this->assertSame('fake', (new FakeScriptGenerationProvider)->name());
    }

    public function test_output_is_deterministic(): void
    {
        $provider = new FakeScriptGenerationProvider;

        $first = $provider->generate($this->request());
        $second = $provider->generate($this->request());

        $this->assertSame($first->toArray(), $second->toArray());
    }

    public function test_output_uses_request_fields(): void
    {
        $request = $this->request();
        $response = (new FakeScriptGenerationProvider)->generate($request);

        $this->assertSame('fake', $response->provider);
        $this->assertSame($request->targetDurationSeconds, $response->durationSeconds);
        $this->assertSame('Tahukah kamu fakta menarik tentang Kenapa kucing suka kardus??', $response->hook);
        $this->assertStringContainsString(
            'Kucing suka kardus karena terasa aman dan nyaman.',
            $response->body
        );
    }

    public function test_hook_style_and_language_flow_through(): void
    {
        $request = new ScriptGenerationRequest(
            topic: 'Kenapa kucing suka kardus?',
            language: 'en',
            hookStyle: 'A short story',
        );
        $response = (new FakeScriptGenerationProvider)->generate($request);

        $this->assertSame('Did you know a fun fact about Kenapa kucing suka kardus??', $response->hook);
        $this->assertStringContainsString('A short story:', $response->body);
    }

    public function test_never_requires_external_http(): void
    {
        Http::preventStrayRequests();

        (new FakeScriptGenerationProvider)->generate($this->request());

        $this->assertTrue(true);
    }

    public function test_output_surfaces_usable_claim_from_research_context(): void
    {
        $request = new ScriptGenerationRequest(
            topic: 'Kenapa kucing suka kardus?',
            researchContext: [
                'usable_claims' => [[
                    'id' => 11,
                    'claim' => 'Kucing suka kardus karena terasa aman.',
                    'importance' => 'high',
                    'status' => 'supported',
                    'classification' => 'usable',
                    'has_evidence' => true,
                    'sources' => [
                        ['id' => 1, 'title' => 'Sumber A', 'domain' => 'cats.example', 'url' => 'https://cats.example/a', 'source_type' => 'article'],
                        ['id' => 2, 'title' => 'Sumber B', 'domain' => 'felines.example', 'url' => 'https://felines.example/b', 'source_type' => 'news'],
                    ],
                ]],
            ],
        );

        $response = (new FakeScriptGenerationProvider)->generate($request);

        $this->assertStringContainsString(
            'Bahan riset terverifikasi: Kucing suka kardus karena terasa aman. (didukung oleh 2 sumber).',
            $response->body
        );
    }

    public function test_unverified_claims_are_never_phrased_as_verified_material(): void
    {
        $unverifiedText = 'Kucing suka kardus klaim belum diverifikasi.';

        $request = new ScriptGenerationRequest(
            topic: 'Kenapa kucing suka kardus?',
            importantClaims: [$unverifiedText],
            researchContext: [
                'usable_claims' => [],
                'claims_requiring_verification' => [[
                    'id' => 1,
                    'claim' => $unverifiedText,
                    'importance' => 'low',
                    'status' => 'unverified',
                    'classification' => 'requires_verification',
                    'has_evidence' => false,
                    'sources' => [],
                ]],
            ],
        );

        $response = (new FakeScriptGenerationProvider)->generate($request);

        $this->assertStringContainsString($unverifiedText, $response->body);
        $this->assertStringNotContainsString('Bahan riset terverifikasi', $response->body);
    }

    public function test_research_context_surfaces_deterministically(): void
    {
        $request = new ScriptGenerationRequest(
            topic: 'Kenapa kucing suka kardus?',
            researchContext: [
                'usable_claims' => [[
                    'id' => 11,
                    'claim' => 'Kucing suka kardus karena terasa aman.',
                    'importance' => 'high',
                    'status' => 'supported',
                    'classification' => 'usable',
                    'has_evidence' => true,
                    'sources' => [
                        ['id' => 1, 'title' => 'Sumber A', 'domain' => 'cats.example', 'url' => 'https://cats.example/a', 'source_type' => 'article'],
                    ],
                ]],
            ],
        );

        $provider = new FakeScriptGenerationProvider;
        $first = $provider->generate($request);
        $second = $provider->generate($request);

        $this->assertSame($first->toArray(), $second->toArray());
    }

    public function test_english_surfaces_usable_claim_in_english(): void
    {
        $request = new ScriptGenerationRequest(
            topic: 'Why do cats purr?',
            language: 'en',
            researchContext: [
                'usable_claims' => [[
                    'id' => 11,
                    'claim' => 'Cats purr to soothe themselves.',
                    'importance' => 'high',
                    'status' => 'supported',
                    'classification' => 'usable',
                    'has_evidence' => true,
                    'sources' => [
                        ['id' => 1, 'title' => 'Vet Journal', 'domain' => 'vet.example', 'url' => 'https://vet.example/a', 'source_type' => 'academic'],
                    ],
                ]],
            ],
        );

        $response = (new FakeScriptGenerationProvider)->generate($request);

        $this->assertStringContainsString(
            'Verified research material: Cats purr to soothe themselves. (backed by 1 source).',
            $response->body
        );
    }
}
