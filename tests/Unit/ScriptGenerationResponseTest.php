<?php

namespace Tests\Unit;

use App\Services\AI\DTO\ScriptGenerationResponse;
use PHPUnit\Framework\TestCase;

class ScriptGenerationResponseTest extends TestCase
{
    public function test_normalizes_strings_and_nullables(): void
    {
        $response = new ScriptGenerationResponse(
            provider: ' fake ',
            hook: '  Hook.  ',
            body: '  Body.  ',
            model: '   ',
            title: '',
            closing: '   ',
            durationSeconds: 30,
            notes: '  ',
        );

        $this->assertSame('fake', $response->provider);
        $this->assertSame('Hook.', $response->hook);
        $this->assertSame('Body.', $response->body);
        $this->assertNull($response->model);
        $this->assertNull($response->title);
        $this->assertNull($response->closing);
        $this->assertNull($response->notes);
        $this->assertSame(30, $response->durationSeconds);
    }

    public function test_empty_raw_metadata_becomes_null(): void
    {
        $response = new ScriptGenerationResponse('fake', 'Hook.', 'Body.', rawMetadata: []);

        $this->assertNull($response->rawMetadata);
    }

    public function test_preserves_non_empty_raw_metadata(): void
    {
        $response = new ScriptGenerationResponse('fake', 'Hook.', 'Body.', rawMetadata: ['usage' => ['tokens' => 42]]);

        $this->assertSame(['usage' => ['tokens' => 42]], $response->rawMetadata);
    }

    public function test_to_array_exposes_normalized_shape(): void
    {
        $response = new ScriptGenerationResponse(
            provider: 'fake',
            model: null,
            title: 'Judul',
            hook: 'Hook.',
            body: 'Body.',
            closing: 'Closing.',
            durationSeconds: 40,
            notes: 'Catatan',
        );

        $this->assertSame([
            'provider' => 'fake',
            'model' => null,
            'title' => 'Judul',
            'hook' => 'Hook.',
            'body' => 'Body.',
            'closing' => 'Closing.',
            'duration_seconds' => 40,
            'notes' => 'Catatan',
        ], $response->toArray());
    }
}
