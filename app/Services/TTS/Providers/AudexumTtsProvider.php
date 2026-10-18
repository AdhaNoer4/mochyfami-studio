<?php

namespace App\Services\TTS\Providers;

use App\Exceptions\TtsProviderException;
use App\Services\TTS\Contracts\TtsProvider;
use App\Services\TTS\DTO\TtsRequest;
use App\Services\TTS\DTO\TtsResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class AudexumTtsProvider implements TtsProvider
{
    public function name(): string
    {
        return 'audexum';
    }

    public function synthesize(TtsRequest $request): TtsResult
    {
        $apiKey = (string) config('tts.audexum.api_key');

        if ($apiKey === '') {
            throw new TtsProviderException(
                'Audexum API key is not configured.',
                TtsProviderException::UNAVAILABLE
            );
        }

        try {
            $response = Http::withToken($apiKey)
                ->accept('audio/wav')
                ->connectTimeout(10)
                ->timeout(120)
                ->post(
                    (string) config(
                        'tts.audexum.endpoint',
                        'https://audexum.com/api/synthesize'
                    ),
                    [
                        'text' => $request->narrationText,
                        'voice' => $request->voice,
                        'lang' => $request->language
                            ?? config('tts.audexum.language', 'id'),
                        'speed' => $request->speed,
                        'format' => 'wav',
                    ]
                );
        } catch (ConnectionException $e) {
            throw new TtsProviderException(
                'Unable to connect to Audexum TTS.',
                TtsProviderException::UNAVAILABLE,
                $e
            );
        }

        if (! $response->successful()) {
            // Do not include the provider response body: it may contain
            // internal details that should not be exposed to the application.
            throw new TtsProviderException(
                'Audexum could not synthesize the narration.',
                TtsProviderException::UNAVAILABLE
            );
        }

        $audio = $response->body();

        // Reject JSON/error pages or any response that is not a WAV file.
        if (
            strlen($audio) < 44
            || substr($audio, 0, 4) !== 'RIFF'
            || substr($audio, 8, 4) !== 'WAVE'
        ) {
            throw new TtsProviderException(
                'Audexum returned an invalid audio file.',
                TtsProviderException::INVALID_RESPONSE
            );
        }

        $reportedDuration = $response->header('X-Audio-Duration-S');

        $durationSeconds = is_numeric($reportedDuration)
            ? max(1, (int) ceil((float) $reportedDuration))
            : max(1, (int) ceil(
                count(preg_split('/\s+/', trim($request->narrationText))) / 3
            ));

        return new TtsResult(
            audio: $audio,
            durationSeconds: $durationSeconds,
            mimeType: 'audio/wav',
            extension: 'wav',
            provider: $this->name(),
            model: $request->model,
        );
    }
}
