<?php

namespace App\Services\TTS\Contracts;

use App\Exceptions\TtsProviderException;
use App\Services\TTS\DTO\TtsRequest;
use App\Services\TTS\DTO\TtsResult;

interface TtsProvider
{
    /**
     * Get the provider's registered name.
     */
    public function name(): string;

    /**
     * Synthesize a narration clip from a provider-independent request.
     *
     * @throws TtsProviderException
     */
    public function synthesize(TtsRequest $request): TtsResult;
}
