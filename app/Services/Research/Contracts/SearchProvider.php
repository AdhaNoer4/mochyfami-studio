<?php

namespace App\Services\Research\Contracts;

use App\Exceptions\SearchProviderException;
use App\Services\Research\DTO\SearchQuery;
use App\Services\Research\DTO\SearchResponse;

interface SearchProvider
{
    /**
     * Get the provider's registered name.
     */
    public function name(): string;

    /**
     * Execute a search and return a normalized, provider-independent response.
     *
     * @throws SearchProviderException
     */
    public function search(SearchQuery $query): SearchResponse;
}
