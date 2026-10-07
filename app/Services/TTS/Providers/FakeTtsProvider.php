<?php

namespace App\Services\TTS\Providers;

use App\Services\TTS\Contracts\TtsProvider;
use App\Services\TTS\DTO\TtsRequest;
use App\Services\TTS\DTO\TtsResult;

/**
 * Deterministic in-memory provider for tests and local development.
 *
 * Performs zero network requests, uses no randomness and no timestamps. It
 * emits a real, playable PCM WAV file of silence whose length follows the
 * Phase 5 rule duration_seconds = ceil(word_count / 3) at roughly 180 words
 * per minute. It only proves the synthesis architecture works — it is not
 * production TTS.
 */
class FakeTtsProvider implements TtsProvider
{
    private const SAMPLE_RATE = 8000;

    private const CHANNELS = 1;

    private const BITS_PER_SAMPLE = 16;

    private const WAV_HEADER_SIZE = 44;

    public function name(): string
    {
        return 'fake';
    }

    public function synthesize(TtsRequest $request): TtsResult
    {
        $durationSeconds = $this->durationFor($request->narrationText);

        return new TtsResult(
            audio: $this->silentWav($durationSeconds),
            durationSeconds: $durationSeconds,
            mimeType: 'audio/wav',
            extension: 'wav',
            provider: $this->name(),
            model: $request->model,
        );
    }

    /**
     * Phase 5 rule: about 3 words per second, so ceil(word_count / 3), and at
     * least one second for any valid narration.
     */
    private function durationFor(string $narrationText): int
    {
        return max(1, (int) ceil($this->wordCount($narrationText) / 3));
    }

    private function wordCount(string $text): int
    {
        return count(preg_split('/\s+/', trim($text)));
    }

    /**
     * A valid 44-byte RIFF/WAVE header followed by zeroed PCM samples, so the
     * header, the duration it advertises and the byte length always agree.
     */
    private function silentWav(int $durationSeconds): string
    {
        $byteRate = self::SAMPLE_RATE * self::CHANNELS * (self::BITS_PER_SAMPLE / 8);
        $blockAlign = self::CHANNELS * (self::BITS_PER_SAMPLE / 8);
        $dataSize = $byteRate * $durationSeconds;

        $header = pack(
            'A4VA4A4VvvVVvvA4V',
            'RIFF',
            36 + $dataSize,
            'WAVE',
            'fmt ',
            16,
            1,
            self::CHANNELS,
            self::SAMPLE_RATE,
            $byteRate,
            $blockAlign,
            self::BITS_PER_SAMPLE,
            'data',
            $dataSize
        );

        return $header.str_repeat("\0", $dataSize);
    }
}
