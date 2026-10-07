<?php

namespace Tests\Unit;

use App\Services\TTS\DTO\TtsRequest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TtsRequestTest extends TestCase
{
    public function test_trims_narration_and_voice_and_applies_defaults(): void
    {
        $request = new TtsRequest('  Narasi untuk voiceover.  ', '  neutral  ');

        $this->assertSame('Narasi untuk voiceover.', $request->narrationText);
        $this->assertSame('neutral', $request->voice);
        $this->assertNull($request->model);
        $this->assertNull($request->language);
        $this->assertSame(TtsRequest::DEFAULT_SPEED, $request->speed);
    }

    public function test_optional_fields_may_be_blank(): void
    {
        $request = new TtsRequest('Narasi.', 'neutral', model: '   ', language: '');

        $this->assertNull($request->model);
        $this->assertNull($request->language);
    }

    public function test_narration_must_not_be_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TtsRequest('   ', 'neutral');
    }

    public function test_voice_must_not_be_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TtsRequest('Narasi.', '   ');
    }

    public function test_narration_must_not_exceed_max_length(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TtsRequest(str_repeat('a', TtsRequest::MAX_NARRATION_LENGTH + 1), 'neutral');
    }

    public function test_voice_must_not_exceed_max_length(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TtsRequest('Narasi.', str_repeat('a', TtsRequest::MAX_VOICE_LENGTH + 1));
    }

    public function test_model_must_not_exceed_max_length(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TtsRequest('Narasi.', 'neutral', model: str_repeat('a', TtsRequest::MAX_MODEL_LENGTH + 1));
    }

    public function test_language_must_not_exceed_max_length(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TtsRequest('Narasi.', 'neutral', language: str_repeat('a', TtsRequest::MAX_LANGUAGE_LENGTH + 1));
    }

    public function test_speed_must_be_within_range(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TtsRequest('Narasi.', 'neutral', speed: TtsRequest::MAX_SPEED + 0.1);
    }

    public function test_speed_can_be_slowed_or_fast_forwarded(): void
    {
        $this->assertSame(0.75, (new TtsRequest('Narasi.', 'neutral', speed: 0.75))->speed);
        $this->assertSame(1.5, (new TtsRequest('Narasi.', 'neutral', speed: 1.5))->speed);
    }
}
