<?php

namespace Tests\Unit;

use App\Services\TTS\DTO\TtsRequest;
use App\Services\TTS\Providers\FakeTtsProvider;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class FakeTtsProviderTest extends TestCase
{
    private function request(int $wordCount, string $voice = 'neutral'): TtsRequest
    {
        return new TtsRequest(implode(' ', array_fill(0, $wordCount, 'kata')), $voice);
    }

    public function test_name(): void
    {
        $this->assertSame('fake', (new FakeTtsProvider)->name());
    }

    public function test_output_is_byte_identical_for_identical_input(): void
    {
        $provider = new FakeTtsProvider;

        $first = $provider->synthesize($this->request(12));
        $second = $provider->synthesize($this->request(12));

        $this->assertSame($first->audio, $second->audio);
        $this->assertSame($first->durationSeconds, $second->durationSeconds);
    }

    public function test_mime_and_extension_are_wav(): void
    {
        $result = (new FakeTtsProvider)->synthesize($this->request(6));

        $this->assertSame('audio/wav', $result->mimeType);
        $this->assertSame('wav', $result->extension);
    }

    #[TestWith([1, 1])]
    #[TestWith([3, 1])]
    #[TestWith([4, 2])]
    #[TestWith([6, 2])]
    #[TestWith([7, 3])]
    #[TestWith([180, 60])]
    #[TestWith([181, 61])]
    public function test_duration_follows_the_word_count_formula(int $words, int $expected): void
    {
        $result = (new FakeTtsProvider)->synthesize($this->request($words));

        $this->assertSame($expected, $result->durationSeconds);
    }

    public function test_audio_is_a_valid_wav_of_the_declared_duration(): void
    {
        $result = (new FakeTtsProvider)->synthesize($this->request(30));
        $audio = $result->audio;

        $this->assertSame('RIFF', substr($audio, 0, 4));
        $this->assertSame('WAVE', substr($audio, 8, 4));
        $this->assertSame('fmt ', substr($audio, 12, 4));

        [$chunkSize] = array_values(unpack('V', substr($audio, 4, 4)));
        [$audioFormat] = array_values(unpack('v', substr($audio, 20, 2)));
        [$channels] = array_values(unpack('v', substr($audio, 22, 2)));
        [$sampleRate] = array_values(unpack('V', substr($audio, 24, 4)));
        [$bitsPerSample] = array_values(unpack('v', substr($audio, 34, 2)));
        [$dataSize] = array_values(unpack('V', substr($audio, 40, 4)));

        $this->assertSame(1, $audioFormat);
        $this->assertSame(1, $channels);
        $this->assertSame(8000, $sampleRate);
        $this->assertSame(16, $bitsPerSample);
        $this->assertSame($result->durationSeconds * 16000, $dataSize);
        $this->assertSame(36 + $dataSize, $chunkSize);
        $this->assertSame(44 + $dataSize, strlen($audio));
    }

    public function test_never_requires_external_http(): void
    {
        Http::preventStrayRequests();

        (new FakeTtsProvider)->synthesize($this->request(6));

        $this->assertTrue(true);
    }
}
