<?php

namespace App\Services\Research\DTO;

class SearchResponse
{
    /**
     * @param  array<int, SearchResult>  $results
     */
    public function __construct(
        public readonly SearchQuery $query,
        public readonly string $provider,
        public readonly array $results,
    ) {}

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'query' => $this->query->query,
            'results' => array_map(
                static fn (SearchResult $result): array => $result->toArray(),
                $this->results
            ),
        ];
    }
}
