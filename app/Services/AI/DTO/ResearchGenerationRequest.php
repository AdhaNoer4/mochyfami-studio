<?php

namespace App\Services\AI\DTO;

use App\Services\AI\Prompt\MochyFamiResearchProfile;
use InvalidArgumentException;

class ResearchGenerationRequest
{
    public const MAX_TOPIC_LENGTH = 300;

    public const MAX_QUESTION_LENGTH = 500;

    public const MAX_CONTEXT_LENGTH = 2000;

    public const DEFAULT_MAX_CLAIMS = 5;

    public const MIN_MAX_CLAIMS = 1;

    public const MAX_MAX_CLAIMS = 25;

    public readonly string $topic;

    public readonly string $question;

    public readonly ?string $context;

    public readonly int $maxClaims;

    public function __construct(string $topic, string $question, ?string $context = null, int $maxClaims = self::DEFAULT_MAX_CLAIMS)
    {
        $this->topic = $this->normalize($topic, self::MAX_TOPIC_LENGTH, 'topic');
        $this->question = $this->normalize($question, self::MAX_QUESTION_LENGTH, 'question');
        $this->context = $this->normalizeNullable($context, self::MAX_CONTEXT_LENGTH, 'context');

        if ($maxClaims < self::MIN_MAX_CLAIMS || $maxClaims > self::MAX_MAX_CLAIMS) {
            throw new InvalidArgumentException(
                'maxClaims must be between '.self::MIN_MAX_CLAIMS.' and '.self::MAX_MAX_CLAIMS.'.'
            );
        }

        $this->maxClaims = $maxClaims;
    }

    public function promptProfile(): string
    {
        return MochyFamiResearchProfile::NAME;
    }

    private function normalize(string $value, int $max, string $field): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException(ucfirst($field).' must not be empty.');
        }

        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException(
                ucfirst($field)." must not exceed {$max} characters."
            );
        }

        return $value;
    }

    private function normalizeNullable(?string $value, int $max, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return $this->normalize($value, $max, $field);
    }
}
