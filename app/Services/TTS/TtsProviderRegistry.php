<?php

namespace App\Services\TTS;

use App\Exceptions\TtsProviderException;
use App\Services\TTS\Contracts\TtsProvider;

class TtsProviderRegistry
{
    /**
     * Map of registered provider names to providers.
     *
     * @var array<string, TtsProvider|class-string<TtsProvider>>
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
     * The default provider is chosen by the caller from config; the registry
     * itself only ever resolves an explicit, registered name.
     *
     * @throws TtsProviderException
     */
    public function resolve(string $name): TtsProvider
    {
        if (! $this->has($name)) {
            throw new TtsProviderException(
                'The requested TTS provider is not available.',
                TtsProviderException::UNKNOWN_PROVIDER
            );
        }

        $provider = $this->providers[$name];

        if (is_string($provider)) {
            $provider = app($provider);
        }

        if (! $provider instanceof TtsProvider) {
            throw new TtsProviderException(
                'The TTS provider is misconfigured.',
                TtsProviderException::INVALID_RESPONSE
            );
        }

        return $provider;
    }
}
