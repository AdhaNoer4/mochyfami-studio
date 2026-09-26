<?php

namespace App\Services\Research;

use App\Exceptions\SearchProviderException;
use App\Services\Research\Contracts\SearchProvider;

class SearchProviderRegistry
{
    /**
     * Map of registered provider names to providers.
     *
     * @var array<string, SearchProvider|class-string<SearchProvider>>
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
     * @throws SearchProviderException
     */
    public function resolve(string $name): SearchProvider
    {
        if (! $this->has($name)) {
            throw new SearchProviderException(
                'The requested search provider is not available.',
                SearchProviderException::UNKNOWN_PROVIDER
            );
        }

        $provider = $this->providers[$name];

        if (is_string($provider)) {
            $provider = app($provider);
        }

        if (! $provider instanceof SearchProvider) {
            throw new SearchProviderException(
                'The search provider is misconfigured.',
                SearchProviderException::INVALID_RESPONSE
            );
        }

        return $provider;
    }
}
