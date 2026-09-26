<?php

namespace App\Services\Research;

use App\Enums\SourceType;
use App\Services\Research\DTO\SearchResult;
use Carbon\CarbonInterface;

class SourceNormalizer
{
    /**
     * Convert a normalized search result into data compatible with the
     * existing Source model without persisting anything.
     *
     * When a source type cannot be reliably determined, the safe database
     * default (other) is used instead of guessing from weak signals.
     *
     * @return array{
     *     title: string,
     *     url: string,
     *     domain: string,
     *     source_type: SourceType,
     *     published_at: CarbonInterface|null
     * }
     */
    public static function forCandidate(SearchResult $result): array
    {
        return [
            'title' => $result->title,
            'url' => $result->url,
            'domain' => $result->domain,
            'source_type' => SourceType::Other,
            'published_at' => $result->publishedAt,
        ];
    }
}
