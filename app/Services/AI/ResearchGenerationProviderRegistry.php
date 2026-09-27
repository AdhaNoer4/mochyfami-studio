<?php

namespace App\Services\AI;

use App\Exceptions\ResearchGenerationProviderException;
use App\Services\AI\Contracts\ResearchGenerationProvider;

class ResearchGenerationProviderRegistry
{
    /**
     * Map of registered provider names to providers.
     *
     * @var array<string, ResearchGenerationProvider|class-string<ResearchGenerationProvider>>
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
     * @throws ResearchGenerationProviderException
     */
    public function resolve(string $name): ResearchGenerationProvider
    {
        if (! $this->has($name)) {
            throw new ResearchGenerationProviderException(
                'The requested research generation provider is not available.',
                ResearchGenerationProviderException::UNKNOWN_PROVIDER
            );
        }

        $provider = $this->providers[$name];

        if (is_string($provider)) {
            $provider = app($provider);
        }

        if (! $provider instanceof ResearchGenerationProvider) {
            throw new ResearchGenerationProviderException(
                'The research generation provider is misconfigured.',
                ResearchGenerationProviderException::INVALID_RESPONSE
            );
        }

        return $provider;
    }
}
