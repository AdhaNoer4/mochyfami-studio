<?php

namespace Tests\Unit;

use App\Services\TTS\DTO\TtsResult;
use PHPUnit\Framework\TestCase;

class TtsResultTest extends TestCase
{
    public function test_exposes_all_synthesis_output_fields(): void
    {
        $result = new TtsResult(
            audio: 'RIFFwave-bytes',
            durationSeconds: 12,
            mimeType: 'audio/wav',
            extension: 'wav',
            provider: 'fake',
            model: 'fake-model',
        );

        $this->assertSame('RIFFwave-bytes', $result->audio);
        $this->assertSame(12, $result->durationSeconds);
        $this->assertSame('audio/wav', $result->mimeType);
        $this->assertSame('wav', $result->extension);
        $this->assertSame('fake', $result->provider);
        $this->assertSame('fake-model', $result->model);
    }

    public function test_blank_model_becomes_null(): void
    {
        $result = new TtsResult(
            audio: 'x',
            durationSeconds: 1,
            mimeType: 'audio/wav',
            extension: 'wav',
            provider: 'fake',
            model: '   ',
        );

        $this->assertNull($result->model);
    }

    public function test_string_metadata_is_trimmed(): void
    {
        $result = new TtsResult(
            audio: 'x',
            durationSeconds: 1,
            mimeType: ' audio/wav ',
            extension: ' wav ',
            provider: ' fake ',
        );

        $this->assertSame('audio/wav', $result->mimeType);
        $this->assertSame('wav', $result->extension);
        $this->assertSame('fake', $result->provider);
    }
}
