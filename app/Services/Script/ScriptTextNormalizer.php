<?php

namespace App\Services\Script;

class ScriptTextNormalizer
{
    /**
     * Common Indonesian stopwords removed before token matching.
     *
     * @var list<string>
     */
    private const STOPWORDS = [
        'yang', 'dan', 'di', 'ke', 'dari', 'pada', 'dengan', 'untuk', 'adalah',
        'ini', 'itu', 'akan', 'tidak', 'juga', 'atau', 'karena', 'bahwa', 'bisa',
        'dapat', 'seperti', 'sebagai', 'dalam', 'ada', 'oleh', 'para', 'bagi',
        'sudah', 'lebih', 'sangat', 'masih', 'harus', 'mungkin',
    ];

    /**
     * Normalize a text: lowercase, remove punctuation, collapse whitespace.
     */
    public function normalizeText(string $text): string
    {
        $lower = mb_strtolower($text);
        $onlyWordChars = preg_replace('/[^a-z0-9\s]/u', ' ', $lower) ?? $lower;
        $collapsed = preg_replace('/\s+/u', ' ', trim($onlyWordChars)) ?? trim($onlyWordChars);

        return $collapsed;
    }

    /**
     * Tokenize text into meaningful tokens: normalized, non-empty, longer
     * than two characters and not an Indonesian stopword.
     *
     * @return list<string>
     */
    public function tokenize(string $text): array
    {
        $normalized = $this->normalizeText($text);

        if ($normalized === '') {
            return [];
        }

        $tokens = preg_split('/\s+/', $normalized) ?: [];

        return array_values(array_filter(
            $tokens,
            fn (string $token): bool => mb_strlen($token) >= 3 && ! in_array($token, self::STOPWORDS, true)
        ));
    }

    /**
     * Count the words in a text (normalized, punctuation ignored).
     */
    public function countWords(string $text): int
    {
        $normalized = $this->normalizeText($text);

        if ($normalized === '') {
            return 0;
        }

        return count(preg_split('/\s+/', $normalized) ?: []);
    }

    /**
     * Join the version content fields into one searchable text.
     */
    public function combinedScriptText(?string $hook, ?string $body, ?string $closing): string
    {
        return trim(implode(' ', array_filter([
            trim((string) $hook),
            trim((string) $body),
            trim((string) $closing),
        ], fn (string $part): bool => $part !== '')));
    }
}
