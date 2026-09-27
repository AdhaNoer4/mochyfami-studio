<?php

namespace App\Services\AI\Contracts;

use App\Exceptions\ResearchGenerationProviderException;
use App\Services\AI\DTO\ResearchGenerationRequest;
use App\Services\AI\DTO\ResearchGenerationResponse;

interface ResearchGenerationProvider
{
    /**
     * Unique provider identifier used for registry lookup.
     */
    public function name(): string;

    /**
     * Generate structured research candidates for a topic/question.
     *
     * The returned response represents AI research CANDIDATES, not verified
     * evidence. Providers must never imply that claims are proven or that
     * sources were verified.
     *
     * @throws ResearchGenerationProviderException
     */
    public function generate(ResearchGenerationRequest $request): ResearchGenerationResponse;
}
