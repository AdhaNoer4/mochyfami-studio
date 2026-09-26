<?php

namespace App\Services\Research;

use App\Exceptions\SearchProviderException;
use App\Services\Research\Contracts\SearchProvider;
use App\Services\Research\DTO\SearchQuery;
use App\Services\Research\DTO\SearchResponse;
use App\Services\Research\DTO\SearchResult;

/**
 * Deterministic in-memory provider for testing and local development.
 *
 * Performs zero network requests and exists only to validate the
 * provider contract — it is not production search.
 */
class FakeSearchProvider implements SearchProvider
{
    /**
     * @param  array<int, SearchResult>  $results
     */
    public function __construct(private array $results = []) {}

    public function name(): string
    {
        return 'fake';
    }

    public function search(SearchQuery $query): SearchResponse
    {
        try {
            $results = array_slice($this->results, 0, $query->maxResults);
        } catch (\Throwable $e) {
            throw new SearchProviderException(
                'The search provider returned an invalid response.',
                SearchProviderException::INVALID_RESPONSE,
                $e
            );
        }

        return new SearchResponse($query, $this->name(), $results);
    }
}
