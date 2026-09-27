<?php

namespace App\Services\AI\DTO;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Enums\SourceType;
use InvalidArgumentException;

/**
 * Normalized output of an AI research generation call.
 *
 * IMPORTANT: the claims inside this response are AI research CANDIDATES.
 * They are always normalized to the "unverified" claim status and must
 * never be treated as verified evidence without human fact-checking.
 */
class ResearchGenerationResponse
{
    public readonly string $provider;

    public readonly ?string $summary;

    /**
     * @var array<int, array{claim: string, importance: ResearchClaimImportance, status: ResearchClaimStatus}>
     */
    public readonly array $claims;

    /**
     * @var array<int, array{title: string, url: string, domain: string|null, source_type: SourceType}>
     */
    public readonly array $sources;

    public readonly ?string $model;

    public readonly ?array $rawMetadata;

    /**
     * @param  array<int, array{claim: string, importance: ResearchClaimImportance|string, source_type?: string, ...}>  $claims
     * @param  array<int, array{title: string, url: string, domain?: string|null, source_type?: string}>  $sources
     */
    public function __construct(
        string $provider,
        ?string $summary,
        array $claims = [],
        array $sources = [],
        ?string $model = null,
        ?array $rawMetadata = null
    ) {
        $this->provider = trim($provider);
        $this->summary = $this->nullableString($summary);
        $this->model = $this->nullableString($model);
        $this->rawMetadata = $rawMetadata === [] ? null : $rawMetadata;

        $normalizedClaims = [];
        foreach ($claims as $index => $claim) {
            if (! isset($claim['claim']) || ! is_string($claim['claim'])) {
                throw new InvalidArgumentException("Research generation claim at index {$index} has no claim text.");
            }

            $normalizedClaims[$index] = [
                'claim' => trim($claim['claim']),
                'importance' => $this->importance($claim['importance'] ?? null),
                'status' => ResearchClaimStatus::Unverified,
            ];
        }

        $this->claims = $normalizedClaims;

        $normalizedSources = [];
        foreach ($sources as $index => $source) {
            foreach (['title', 'url'] as $field) {
                if (! isset($source[$field]) || ! is_string($source[$field])) {
                    throw new InvalidArgumentException(
                        "Research generation source at index {$index} has no {$field}."
                    );
                }
            }

            $normalizedSources[$index] = [
                'title' => trim($source['title']),
                'url' => trim($source['url']),
                'domain' => $this->nullableString($source['domain'] ?? null),
                'source_type' => $this->sourceType($source['source_type'] ?? null),
            ];
        }

        $this->sources = $normalizedSources;
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'summary' => $this->summary,
            'model' => $this->model,
            'claims' => array_map(
                fn (array $claim): array => [
                    'claim' => $claim['claim'],
                    'importance' => $claim['importance']->value,
                    'status' => $claim['status']->value,
                ],
                $this->claims
            ),
            'sources' => array_map(
                fn (array $source): array => [
                    'title' => $source['title'],
                    'url' => $source['url'],
                    'domain' => $source['domain'],
                    'source_type' => $source['source_type']->value,
                ],
                $this->sources
            ),
        ];
    }

    private function nullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function importance(mixed $importance): ResearchClaimImportance
    {
        if ($importance instanceof ResearchClaimImportance) {
            return $importance;
        }

        $enum = ResearchClaimImportance::tryFrom(is_string($importance) ? strtolower($importance) : '');

        if ($enum === null) {
            throw new InvalidArgumentException('Research generation claim has an invalid importance.');
        }

        return $enum;
    }

    private function sourceType(mixed $sourceType): SourceType
    {
        if ($sourceType instanceof SourceType) {
            return $sourceType;
        }

        return SourceType::tryFrom(is_string($sourceType) ? strtolower($sourceType) : '') ?? SourceType::Other;
    }
}
