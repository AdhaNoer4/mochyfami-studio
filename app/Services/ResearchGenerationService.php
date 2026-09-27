<?php

namespace App\Services;

use App\Enums\AiGenerationStatus;
use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Enums\SourceType;
use App\Exceptions\ResearchGenerationProviderException;
use App\Models\AiGeneration;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Source;
use App\Services\AI\DTO\ResearchGenerationRequest;
use App\Services\AI\DTO\ResearchGenerationResponse;
use App\Services\AI\Prompt\MochyFamiResearchProfile;
use App\Services\AI\ResearchGenerationProviderRegistry;
use App\Services\Research\Support\DomainExtractor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ResearchGenerationService
{
    /**
     * Principled bounds for persisted candidates.
     *
     * AI output is never trusted as evidence: claims stay unverified, are
     * never attached to sources automatically, and sources are only stored
     * as candidate rows. Purposefully never exceed the request's max_claims.
     */
    public const MAX_CLAIM_LENGTH = 1000;

    public const MAX_SOURCE_TITLE_LENGTH = 255;

    public const MIN_CLAIMS = 1;

    public function __construct(
        protected ResearchService $researchService,
        protected ResearchGenerationProviderRegistry $registry,
        protected ResearchQualityService $qualityService
    ) {}

    /**
     * Generate research candidates for a project and persist them as
     * unverified candidates inside a single transaction.
     *
     * Existing claims/sources are never touched. Claim status, research
     * status, evidence relations and the curated research summary are never
     * modified. The quality gate is re-evaluated and remains authoritative.
     *
     * @param  array{topic: string, question: string, context?: string|null, max_claims?: int, provider?: string|null}  $input
     * @return array{
     *     report: ResearchReport,
     *     generated_claims: Collection<int, ResearchClaim>,
     *     generated_sources: Collection<int, Source>,
     *     generation: array{provider: string, model: string|null, prompt_profile: string, prompt_version: string, status: string, research_report_id: int},
     *     summary: string|null,
     *     quality: array<string, mixed>
     * }
     *
     * @throws ModelNotFoundException
     * @throws ResearchGenerationProviderException
     */
    public function generate(ContentProject $project, array $input): array
    {
        $report = $this->researchService->getReportOrFail($project);

        $request = $this->buildRequest($input);

        $providerName = $input['provider'] ?? config('ai.research.default_provider', 'fake');
        $provider = $this->registry->resolve($providerName);

        try {
            $response = $provider->generate($request);
        } catch (ResearchGenerationProviderException $e) {
            $this->recordFailure($report, $providerName);
            throw $e;
        } catch (InvalidArgumentException $e) {
            $this->recordFailure($report, $providerName);
            throw new ResearchGenerationProviderException(
                'The research generation provider returned an invalid response.',
                ResearchGenerationProviderException::INVALID_RESPONSE,
                $e
            );
        }

        try {
            $this->assertValidOutput($response, $request);
        } catch (InvalidArgumentException $e) {
            $this->recordFailure($report, $providerName);
            throw new ResearchGenerationProviderException(
                'The research generation provider returned an invalid response.',
                ResearchGenerationProviderException::INVALID_RESPONSE,
                $e
            );
        }

        [$generatedClaims, $generatedSources, $generation] = DB::transaction(function () use ($report, $response, $providerName) {
            $claims = $this->createClaims($report, $response->claims);
            $sources = $this->createSources($report, $response->sources);
            $generation = $this->createCompletedAudit($report, $providerName, $response);

            return [$claims, $sources, $generation];
        });

        return [
            'report' => $report->fresh()->load(['project', 'claims.sources', 'sources']),
            'generated_claims' => $generatedClaims,
            'generated_sources' => $generatedSources,
            'generation' => $generation,
            'summary' => $response->summary,
            'quality' => $this->qualityService->evaluateReport($report->fresh()),
        ];
    }

    /**
     * @param  array{topic: string, question: string, context?: string|null, max_claims?: int}  $input
     */
    private function buildRequest(array $input): ResearchGenerationRequest
    {
        return new ResearchGenerationRequest(
            topic: $input['topic'],
            question: $input['question'],
            context: $input['context'] ?? null,
            maxClaims: (int) ($input['max_claims'] ?? ResearchGenerationRequest::DEFAULT_MAX_CLAIMS),
        );
    }

    /**
     * Structural safety checks before anything is persisted.
     *
     * @throws InvalidArgumentException
     */
    private function assertValidOutput(ResearchGenerationResponse $response, ResearchGenerationRequest $request): void
    {
        if (count($response->claims) < self::MIN_CLAIMS) {
            throw new InvalidArgumentException('The provider returned no research claims.');
        }

        if (count($response->claims) > $request->maxClaims) {
            throw new InvalidArgumentException(
                'The provider returned '.count($response->claims).' claims, exceeding the requested '.$request->maxClaims.'.'
            );
        }

        foreach ($response->claims as $index => $claim) {
            $length = mb_strlen($claim['claim']);

            if ($length === 0) {
                throw new InvalidArgumentException("Research claim at index {$index} is empty.");
            }

            if ($length > self::MAX_CLAIM_LENGTH) {
                throw new InvalidArgumentException(
                    "Research claim at index {$index} exceeds ".self::MAX_CLAIM_LENGTH.' characters.'
                );
            }

            if ($claim['status'] !== ResearchClaimStatus::Unverified) {
                throw new InvalidArgumentException(
                    "Research claim at index {$index} must remain unverified."
                );
            }
        }

        foreach ($response->sources as $index => $source) {
            $titleLength = mb_strlen($source['title']);

            if ($titleLength === 0) {
                throw new InvalidArgumentException("Research source at index {$index} has an empty title.");
            }

            if ($titleLength > self::MAX_SOURCE_TITLE_LENGTH) {
                throw new InvalidArgumentException(
                    "Research source at index {$index} title exceeds ".self::MAX_SOURCE_TITLE_LENGTH.' characters.'
                );
            }

            if (! filter_var($source['url'], FILTER_VALIDATE_URL) || ! DomainExtractor::isHttpUrl($source['url'])) {
                throw new InvalidArgumentException("Research source at index {$index} has an invalid URL.");
            }
        }
    }

    /**
     * @param  array<int, array{claim: string, importance: ResearchClaimImportance, status: ResearchClaimStatus}>  $responseClaims
     * @return Collection<int, ResearchClaim>
     */
    private function createClaims($report, array $responseClaims): Collection
    {
        $created = [];

        foreach ($responseClaims as $claim) {
            $created[] = $report->claims()->create([
                'claim' => $claim['claim'],
                'status' => ResearchClaimStatus::Unverified,
                'importance' => $claim['importance'],
            ]);
        }

        return new Collection($created);
    }

    /**
     * Persist candidate source rows. These are never automatically attached
     * to any claim and never treated as verified evidence.
     *
     * @param  array<int, array{title: string, url: string, domain: string|null, source_type: SourceType}>  $responseSources
     * @return Collection<int, Source>
     */
    private function createSources($report, array $responseSources): Collection
    {
        $created = [];

        foreach ($responseSources as $source) {
            $created[] = $report->sources()->create([
                'title' => $source['title'],
                'url' => $source['url'],
                'domain' => $source['domain'] ?? DomainExtractor::fromUrl($source['url']),
                'source_type' => $source['source_type'] ?? SourceType::Other,
                'published_at' => null,
            ]);
        }

        return new Collection($created);
    }

    private function createCompletedAudit($report, string $providerName, ResearchGenerationResponse $response): array
    {
        $record = AiGeneration::create([
            'script_id' => null,
            'research_report_id' => $report->id,
            'provider' => $providerName,
            'model' => $response->model,
            'prompt_profile' => MochyFamiResearchProfile::PROFILE,
            'prompt_version' => MochyFamiResearchProfile::VERSION,
            'source_version_id' => null,
            'generated_version_id' => null,
            'status' => AiGenerationStatus::Completed,
        ]);

        return [
            'provider' => $record->provider,
            'model' => $record->model,
            'prompt_profile' => $record->prompt_profile,
            'prompt_version' => $record->prompt_version,
            'status' => $record->status->value,
            'research_report_id' => $record->research_report_id,
        ];
    }

    private function recordFailure($report, string $providerName): void
    {
        AiGeneration::create([
            'script_id' => null,
            'research_report_id' => $report->id,
            'provider' => $providerName,
            'model' => null,
            'prompt_profile' => MochyFamiResearchProfile::PROFILE,
            'prompt_version' => MochyFamiResearchProfile::VERSION,
            'source_version_id' => null,
            'generated_version_id' => null,
            'status' => AiGenerationStatus::Failed,
        ]);
    }
}
