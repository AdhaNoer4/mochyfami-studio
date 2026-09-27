<?php

namespace App\Services\AI\DTO;

use App\Services\AI\Prompt\MochyFamiScriptProfile;
use InvalidArgumentException;

/**
 * Immutable, provider-independent description of what MochyFami wants a
 * provider to generate. It never carries vendor-specific request structure.
 */
class ScriptGenerationRequest
{
    public const MAX_TOPIC_LENGTH = 300;

    public const MAX_LANGUAGE_LENGTH = 20;

    public const MAX_TONE_LENGTH = 30;

    public const MAX_FORMAT_LENGTH = 40;

    public const MAX_HOOK_STYLE_LENGTH = 100;

    public const MAX_INSTRUCTIONS_LENGTH = 2000;

    public const MAX_CLAIM_LENGTH = 500;

    public const MAX_IMPORTANT_CLAIMS = 50;

    public const MIN_DURATION_SECONDS = 1;

    public const MAX_DURATION_SECONDS = 600;

    public const DEFAULT_DURATION_SECONDS = 30;

    public readonly string $topic;

    public readonly string $language;

    public readonly string $tone;

    public readonly string $format;

    public readonly int $targetDurationSeconds;

    public readonly ?string $hookStyle;

    public readonly ?string $instructions;

    /**
     * @var array<int, string>
     */
    public readonly array $importantClaims;

    /**
     * Authoritative research-to-script context (see ResearchToScriptContext).
     *
     * @var array<string, mixed>
     */
    public readonly array $researchContext;

    public readonly string $promptProfile;

    public function __construct(
        string $topic,
        string $language = 'id',
        string $tone = 'casual',
        string $format = 'educational',
        int $targetDurationSeconds = self::DEFAULT_DURATION_SECONDS,
        ?string $hookStyle = null,
        ?string $instructions = null,
        array $importantClaims = [],
        array $researchContext = [],
        string $promptProfile = MochyFamiScriptProfile::NAME,
    ) {
        $this->topic = $this->normalizeString($topic, 'topic', self::MAX_TOPIC_LENGTH, required: true);
        $this->language = $this->normalizeString($language, 'language', self::MAX_LANGUAGE_LENGTH, 'id');
        $this->tone = $this->normalizeString($tone, 'tone', self::MAX_TONE_LENGTH, 'casual');
        $this->format = $this->normalizeString($format, 'format', self::MAX_FORMAT_LENGTH, 'educational');
        $this->targetDurationSeconds = $targetDurationSeconds;
        $this->hookStyle = $this->nullableString($hookStyle, 'hook_style', self::MAX_HOOK_STYLE_LENGTH);
        $this->instructions = $this->nullableString($instructions, 'instructions', self::MAX_INSTRUCTIONS_LENGTH);
        $this->importantClaims = $importantClaims;
        $this->researchContext = $researchContext;
        $this->promptProfile = $this->normalizeString($promptProfile, 'prompt_profile', 100, MochyFamiScriptProfile::NAME);

        if ($this->targetDurationSeconds < self::MIN_DURATION_SECONDS || $this->targetDurationSeconds > self::MAX_DURATION_SECONDS) {
            throw new InvalidArgumentException(
                sprintf('target_duration_seconds must be between %d and %d.', self::MIN_DURATION_SECONDS, self::MAX_DURATION_SECONDS)
            );
        }

        if (count($this->importantClaims) > self::MAX_IMPORTANT_CLAIMS) {
            throw new InvalidArgumentException(
                sprintf('important_claims must not exceed %d items.', self::MAX_IMPORTANT_CLAIMS)
            );
        }

        foreach ($this->importantClaims as $claim) {
            if (! is_string($claim) || trim($claim) === '' || mb_strlen(trim($claim)) > self::MAX_CLAIM_LENGTH) {
                throw new InvalidArgumentException('important_claims must be non-empty strings within reasonable length.');
            }
        }
    }

    private function normalizeString(string $value, string $field, int $max, string $default = '', bool $required = false): string
    {
        $value = trim($value);

        if ($value === '' && ! $required) {
            $value = $default;
        }

        if ($value === '') {
            throw new InvalidArgumentException(sprintf('%s must not be empty.', $field));
        }

        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException(sprintf('%s must not exceed %d characters.', $field, $max));
        }

        return $value;
    }

    private function nullableString(?string $value, string $field, int $max): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException(sprintf('%s must not exceed %d characters.', $field, $max));
        }

        return $value;
    }
}
