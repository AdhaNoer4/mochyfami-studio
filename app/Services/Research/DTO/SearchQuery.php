<?php

namespace App\Services\Research\DTO;

use InvalidArgumentException;

class SearchQuery
{
    public const MAX_QUERY_LENGTH = 500;

    public const MAX_RESULTS_LIMIT = 20;

    public const DEFAULT_MAX_RESULTS = 10;

    public readonly string $query;

    public readonly int $maxResults;

    public function __construct(string $query, int $maxResults = self::DEFAULT_MAX_RESULTS)
    {
        $query = trim($query);

        if ($query === '') {
            throw new InvalidArgumentException('Search query must not be empty.');
        }

        if (mb_strlen($query) > self::MAX_QUERY_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Search query must not exceed %d characters.', self::MAX_QUERY_LENGTH)
            );
        }

        if ($maxResults < 1 || $maxResults > self::MAX_RESULTS_LIMIT) {
            throw new InvalidArgumentException(
                sprintf('max_results must be between 1 and %d.', self::MAX_RESULTS_LIMIT)
            );
        }

        $this->query = $query;
        $this->maxResults = $maxResults;
    }
}
