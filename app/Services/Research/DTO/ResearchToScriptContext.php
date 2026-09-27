<?php

namespace App\Services\Research\DTO;

use InvalidArgumentException;

/**
 * Normalized, deterministic research context handed to the script domain.
 *
 * This is the ONE authoritative bridge between research and script phases.
 * It is built exclusively from existing database state through the existing
 * quality and pipeline services. It never fabricates facts, never mutates
 * state and never replaces claim statuses or the research workflow.
 *
 * Claim classification:
 *  - usable: supported by a human and backed by evidence sources
 *  - requires_verification: still unverified or uncertain
 *  - contradicted: contradicted by evidence, never usable
 *  - unsupported: supported in status but carrying no evidence
 */
class ResearchToScriptContext
{
    public const CLASSIFICATION_USABLE = 'usable';

    public const CLASSIFICATION_REQUIRES_VERIFICATION = 'requires_verification';

    public const CLASSIFICATION_CONTRADICTED = 'contradicted';

    public const CLASSIFICATION_UNSUPPORTED = 'unsupported';

    public readonly array $report;

    /**
     * @var array<int, array{id: int, claim: string, importance: string, status: string, classification: string, has_evidence: bool, sources: array<int, array{id: int, title: string, domain: string|null, url: string, source_type: string}>}>
     */
    public readonly array $claims;

    /**
     * @var array<string, mixed>
     */
    public readonly array $quality;

    /**
     * @var array<string, mixed>
     */
    public readonly array $pipeline;

    /**
     * @var array<int, array{id: int, claim: string, importance: string, status: string, classification: string, has_evidence: bool, sources: array<int, array{id: int, title: string, domain: string|null, url: string, source_type: string}>}>
     */
    public readonly array $usableClaims;

    /**
     * @var array<int, array{id: int, claim: string, importance: string, status: string, classification: string, has_evidence: bool, sources: array<int, array{id: int, title: string, domain: string|null, url: string, source_type: string}>}>
     */
    public readonly array $claimsRequiringVerification;

    /**
     * @var array<int, array{id: int, claim: string, importance: string, status: string, classification: string, has_evidence: bool, sources: array<int, array{id: int, title: string, domain: string|null, url: string, source_type: string}>}>
     */
    public readonly array $contradictedClaims;

    /**
     * @var array<int, array{id: int, claim: string, importance: string, status: string, classification: string, has_evidence: bool, sources: array<int, array{id: int, title: string, domain: string|null, url: string, source_type: string}>}>
     */
    public readonly array $unsupportedClaims;

    /**
     * @param  array{id: int, summary: string|null, status: string, quality_ready: bool}  $report
     * @param  array<int, array{id: int, claim: string, importance: string, status: string, classification: string, has_evidence: bool, sources?: array<int, array{id?: int, title?: string, domain?: string|null, url?: string, source_type?: string}>}>  $claims
     * @param  array<string, mixed>  $quality
     * @param  array<string, mixed>  $pipeline
     */
    public function __construct(array $report, array $claims, array $quality, array $pipeline)
    {
        $this->report = $report;
        $this->claims = $this->normalizeClaims($claims);
        $this->quality = $quality;
        $this->pipeline = $pipeline;

        $this->usableClaims = $this->bucket(self::CLASSIFICATION_USABLE);
        $this->claimsRequiringVerification = $this->bucket(self::CLASSIFICATION_REQUIRES_VERIFICATION);
        $this->contradictedClaims = $this->bucket(self::CLASSIFICATION_CONTRADICTED);
        $this->unsupportedClaims = $this->bucket(self::CLASSIFICATION_UNSUPPORTED);
    }

    public function toArray(): array
    {
        return [
            'report' => array_merge($this->report, [
                'counts' => [
                    'total_claims' => count($this->claims),
                    'usable_claims' => count($this->usableClaims),
                    'claims_requiring_verification' => count($this->claimsRequiringVerification),
                    'contradicted_claims' => count($this->contradictedClaims),
                    'unsupported_claims' => count($this->unsupportedClaims),
                    'evidence_backed_claims' => count(array_filter($this->claims, fn (array $claim): bool => $claim['has_evidence'])),
                ],
            ]),
            'claims' => $this->claims,
            'usable_claims' => $this->usableClaims,
            'claims_requiring_verification' => $this->claimsRequiringVerification,
            'contradicted_claims' => $this->contradictedClaims,
            'unsupported_claims' => $this->unsupportedClaims,
            'quality' => $this->quality,
            'pipeline' => $this->pipeline,
        ];
    }

    public static function classifications(): array
    {
        return [
            self::CLASSIFICATION_USABLE,
            self::CLASSIFICATION_REQUIRES_VERIFICATION,
            self::CLASSIFICATION_CONTRADICTED,
            self::CLASSIFICATION_UNSUPPORTED,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $claims
     * @return array<int, array{id: int, claim: string, importance: string, status: string, classification: string, has_evidence: bool, sources: array<int, array{id: int, title: string, domain: string|null, url: string, source_type: string}>}>
     */
    private function normalizeClaims(array $claims): array
    {
        $normalized = [];

        foreach (array_values($claims) as $index => $claim) {
            foreach (['id', 'claim', 'importance', 'status', 'classification', 'has_evidence'] as $field) {
                if (! array_key_exists($field, $claim)) {
                    throw new InvalidArgumentException(
                        "Research claim at index {$index} is missing the '{$field}' field."
                    );
                }
            }

            if (! is_int($claim['id'])) {
                throw new InvalidArgumentException("Research claim at index {$index} has a non-integer id.");
            }

            if (! is_string($claim['claim']) || trim($claim['claim']) === '') {
                throw new InvalidArgumentException("Research claim at index {$index} has no claim text.");
            }

            if (! in_array($claim['classification'], self::classifications(), true)) {
                throw new InvalidArgumentException(
                    "Research claim at index {$index} has an invalid classification '{$claim['classification']}'."
                );
            }

            $sources = $this->normalizeSourceSummaries($claim['sources'] ?? [], $index);

            $normalized[$index] = [
                'id' => $claim['id'],
                'claim' => $claim['claim'],
                'importance' => $claim['importance'],
                'status' => $claim['status'],
                'classification' => $claim['classification'],
                'has_evidence' => (bool) $claim['has_evidence'],
                'sources' => $sources,
            ];
        }

        return array_values($normalized);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sources
     * @return array<int, array{id: int, title: string, domain: string|null, url: string, source_type: string}>
     */
    private function normalizeSourceSummaries(array $sources, int $claimIndex): array
    {
        $normalized = [];

        foreach (array_values($sources) as $index => $source) {
            foreach (['id', 'title', 'domain', 'url', 'source_type'] as $field) {
                if (! array_key_exists($field, $source)) {
                    throw new InvalidArgumentException(
                        "Evidence source at index {$index} for claim {$claimIndex} is missing the '{$field}' field."
                    );
                }
            }

            $normalized[$index] = [
                'id' => (int) $source['id'],
                'title' => (string) $source['title'],
                'domain' => $source['domain'] === null ? null : (string) $source['domain'],
                'url' => (string) $source['url'],
                'source_type' => (string) $source['source_type'],
            ];
        }

        return $normalized;
    }

    /**
     * @return array<int, array{id: int, claim: string, importance: string, status: string, classification: string, has_evidence: bool, sources: array<int, array{id: int, title: string, domain: string|null, url: string, source_type: string}>}>
     */
    private function bucket(string $classification): array
    {
        return array_values(array_filter(
            $this->claims,
            fn (array $claim): bool => $claim['classification'] === $classification
        ));
    }
}
