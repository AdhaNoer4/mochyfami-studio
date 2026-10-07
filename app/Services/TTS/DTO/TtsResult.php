<?php

namespace App\Services\TTS\DTO;

/**
 * A normalized, provider-independent synthesis result.
 *
 * The provider returns raw audio bytes plus the few facts the storage layer
 * needs (Part 2), without ever knowing about Laravel Storage or the Asset
 * model. The provider-declared duration is authoritative at this layer; a
 * later part may reconcile it with a real measurement of the stored file.
 */
class TtsResult
{
    public readonly string $audio;

    public readonly int $durationSeconds;

    public readonly string $mimeType;

    public readonly string $extension;

    public readonly string $provider;

    public readonly ?string $model;

    public function __construct(
        string $audio,
        int $durationSeconds,
        string $mimeType,
        string $extension,
        string $provider,
        ?string $model = null,
    ) {
        $this->audio = $audio;
        $this->durationSeconds = $durationSeconds;
        $this->mimeType = trim($mimeType);
        $this->extension = trim($extension);
        $this->provider = trim($provider);
        $this->model = $this->nullable(trim((string) $model));
    }

    private function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }
}
