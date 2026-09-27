<?php

namespace App\Services\AI\DTO;

/**
 * A normalized, provider-independent script result.
 *
 * The adapter converting provider-specific output into this DTO is
 * responsible for producing structured data; the core application never
 * parses raw provider blobs.
 */
class ScriptGenerationResponse
{
    public readonly string $provider;

    public readonly ?string $model;

    public readonly ?string $title;

    public readonly string $hook;

    public readonly string $body;

    public readonly ?string $closing;

    public readonly ?int $durationSeconds;

    public readonly ?string $notes;

    /**
     * Optional provider metadata. Never persisted; used only transiently.
     *
     * @var array<string, mixed>|null
     */
    public readonly ?array $rawMetadata;

    /**
     * @param  array<string, mixed>|null  $rawMetadata
     */
    public function __construct(
        string $provider,
        string $hook,
        string $body,
        ?string $model = null,
        ?string $title = null,
        ?string $closing = null,
        ?int $durationSeconds = null,
        ?string $notes = null,
        ?array $rawMetadata = null,
    ) {
        $this->provider = trim($provider);
        $this->hook = trim($hook);
        $this->body = trim($body);
        $this->model = $this->nullable(trim((string) $model));
        $this->title = $this->nullable(trim((string) $title));
        $this->closing = $this->nullable(trim((string) $closing));
        $this->durationSeconds = $durationSeconds;
        $this->notes = $this->nullable(trim((string) $notes));
        $this->rawMetadata = $rawMetadata === [] ? null : $rawMetadata;
    }

    private function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    /**
     * @return array{provider: string, model: string|null, title: string|null, hook: string, body: string, closing: string|null, duration_seconds: int|null, notes: string|null}
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'model' => $this->model,
            'title' => $this->title,
            'hook' => $this->hook,
            'body' => $this->body,
            'closing' => $this->closing,
            'duration_seconds' => $this->durationSeconds,
            'notes' => $this->notes,
        ];
    }
}
