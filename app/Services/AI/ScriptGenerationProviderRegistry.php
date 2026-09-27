<?php

namespace App\Services\AI;

use App\Exceptions\ScriptGenerationProviderException;
use App\Services\AI\Contracts\ScriptGenerationProvider;

class ScriptGenerationProviderRegistry
{
    /**
     * Map of registered provider names to providers.
     *
     * @var array<string, ScriptGenerationProvider|class-string<ScriptGenerationProvider>>
     */
    private array $providers;

    public function __construct(array $providers = [])
    {
        $this->providers = $providers;
    }

    /**
     * Get all registered provider names.
     *
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->providers);
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->providers);
    }

    /**
     * Resolve a provider by its registered name.
     *
     * @throws ScriptGenerationProviderException
     */
    public function resolve(string $name): ScriptGenerationProvider
    {
        if (! $this->has($name)) {
            throw new ScriptGenerationProviderException(
                'The requested script generation provider is not available.',
                ScriptGenerationProviderException::UNKNOWN_PROVIDER
            );
        }

        $provider = $this->providers[$name];

        if (is_string($provider)) {
            $provider = app($provider);
        }

        if (! $provider instanceof ScriptGenerationProvider) {
            throw new ScriptGenerationProviderException(
                'The script generation provider is misconfigured.',
                ScriptGenerationProviderException::INVALID_RESPONSE
            );
        }

        return $provider;
    }
}
