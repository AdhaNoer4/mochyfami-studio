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
}
