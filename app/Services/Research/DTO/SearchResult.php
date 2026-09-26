<?php

namespace App\Services\Research\DTO;

use App\Services\Research\Support\DomainExtractor;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class SearchResult
{
    public readonly string $domain;

    public function __construct(
        public readonly string $title,
        public readonly string $url,
        public readonly ?string $snippet = null,
        public readonly ?CarbonInterface $publishedAt = null,
    ) {
        $domain = DomainExtractor::fromUrl($url);

        if ($domain === null) {
            throw new InvalidArgumentException('Search result URL must be a valid HTTP/HTTPS URL.');
        }

        $this->domain = $domain;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'url' => $this->url,
            'snippet' => $this->snippet,
            'domain' => $this->domain,
            'published_at' => $this->publishedAt?->toIso8601String(),
        ];
    }
}
