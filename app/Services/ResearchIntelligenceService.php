<?php

namespace App\Services;

use App\Exceptions\SearchProviderException;
use App\Models\ResearchReport;
use App\Services\Research\DTO\SearchQuery;
use App\Services\Research\DTO\SearchResponse;
use App\Services\Research\SearchProviderRegistry;
use InvalidArgumentException;

class ResearchIntelligenceService
{
    public function __construct(
        protected SearchProviderRegistry $registry
    ) {}

    /**
     * Discover candidate sources for a research report.
     *
     * The report parameter is kept for authorization/domain context and
     * future extensibility. This method never persists anything: it does
     * not create sources or claims, attach evidence, or mutate research
     * status or quality.
     *
     * @throws SearchProviderException
     */
    public function discover(
        ResearchReport $report,
        SearchQuery $query,
        ?string $provider = null
    ): SearchResponse {
        $provider = $provider ?? config('research.search.default_provider', 'fake');

        $searchProvider = $this->registry->resolve($provider);

        try {
            return $searchProvider->search($query);
        } catch (SearchProviderException $e) {
            throw $e;
        } catch (InvalidArgumentException $e) {
            throw new SearchProviderException(
                'The search provider returned an invalid response.',
                SearchProviderException::INVALID_RESPONSE,
                $e
            );
        }
    }
}
