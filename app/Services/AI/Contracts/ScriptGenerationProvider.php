<?php

namespace App\Services\AI\Contracts;

use App\Exceptions\ScriptGenerationProviderException;
use App\Services\AI\DTO\ScriptGenerationRequest;
use App\Services\AI\DTO\ScriptGenerationResponse;

interface ScriptGenerationProvider
{
    /**
     * Get the provider's registered name.
     */
    public function name(): string;

    /**
     * Generate a structured script from a provider-independent request.
     *
     * @throws ScriptGenerationProviderException
     */
    public function generate(ScriptGenerationRequest $request): ScriptGenerationResponse;
}
