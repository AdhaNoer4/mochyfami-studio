<?php

namespace App\Services\TTS\DTO;

use InvalidArgumentException;

/**
 * Immutable, provider-independent description of a single narration clip to
 * synthesize. It never carries vendor-specific request structure and never
 * references Eloquent models: a provider must be able to synthesize from
 * exactly this.
 */
class TtsRequest
{
    public const MAX_NARRATION_LENGTH = 5000;

    public const MAX_VOICE_LENGTH = 100;

    public const MAX_MODEL_LENGTH = 100;

    public const MAX_LANGUAGE_LENGTH = 20;

    public const DEFAULT_SPEED = 1.0;

    public const MIN_SPEED = 0.5;

    public const MAX_SPEED = 2.0;

    public readonly string $narrationText;

    public readonly ?string $model;

    public readonly string $voice;

    public readonly ?string $language;

    public readonly float $speed;

    public function __construct(
        string $narrationText,
        string $voice,
        ?string $model = null,
        ?string $language = null,
        float $speed = self::DEFAULT_SPEED,
    ) {
        $this->narrationText = $this->normalizeString($narrationText, 'narration_text', self::MAX_NARRATION_LENGTH, required: true);
        $this->voice = $this->normalizeString($voice, 'voice', self::MAX_VOICE_LENGTH, required: true);
        $this->model = $this->nullableString($model, 'model', self::MAX_MODEL_LENGTH);
        $this->language = $this->nullableString($language, 'language', self::MAX_LANGUAGE_LENGTH);
        $this->speed = $speed;

        if ($this->speed < self::MIN_SPEED || $this->speed > self::MAX_SPEED) {
            throw new InvalidArgumentException(
                sprintf('speed must be between %s and %s.', self::MIN_SPEED, self::MAX_SPEED)
            );
        }
    }

    private function normalizeString(string $value, string $field, int $max, bool $required = false): string
    {
        $value = trim($value);

        if ($value === '') {
            if ($required) {
                throw new InvalidArgumentException(sprintf('%s must not be empty.', $field));
            }

            return $value;
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
