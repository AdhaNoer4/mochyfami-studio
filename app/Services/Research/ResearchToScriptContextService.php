<?php

namespace App\Services\Research;

use App\Enums\ResearchClaimStatus;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Source;
use App\Services\Research\DTO\ResearchToScriptContext;
use App\Services\ResearchPipelineService;
use App\Services\ResearchQualityService;

/**
 * Builds the single authoritative research-to-script context.
 *
 * Composes the existing deterministic quality and pipeline services without
 * duplicating their logic. The classification rules are the only bridge
 * specific behavior and are kept pure and unit-testable.
 */
class ResearchToScriptContextService
{
    public function __construct(
        protected ResearchQualityService $qualityService,
        protected ResearchPipelineService $pipelineService,
    ) {}

    public function build(ResearchReport $report): ResearchToScriptContext
    {
        $quality = $this->qualityService->evaluateReport($report);
        $pipeline = $this->pipelineService->evaluate($report);

        $claims = $report->claims
            ->sortBy('id')
            ->values()
            ->map(fn (ResearchClaim $claim): array => $this->claimEntry($claim))
            ->all();

        return new ResearchToScriptContext(
            report: [
                'id' => $report->id,
                'summary' => $report->summary,
                'status' => $report->status->value,
                'quality_ready' => $quality['ready'],
            ],
            claims: $claims,
            quality: [
                'ready' => $quality['ready'],
                'score' => $quality['score'],
                'summary' => $quality['summary'],
                'blockers' => $this->issuesBySeverity($quality, 'blocker'),
                'warnings' => $this->issuesBySeverity($quality, 'warning'),
            ],
            pipeline: [
                'stage' => $pipeline['stage'],
                'stage_label' => $pipeline['stage_label'],
                'progress' => $pipeline['progress'],
                'ready_for_script' => $pipeline['ready_for_script'],
                'next_actions' => $pipeline['next_actions'],
            ],
        );
    }

    /**
     * Classify a claim for the script domain based on its stored state.
     *
     * Deterministic and pure. Contradicted and unverified/uncertain states
     * always take precedence over evidence presence; a supported claim only
     * becomes usable when it is actually backed by evidence sources.
     */
    public function classify(ResearchClaimStatus $status, bool $hasEvidence): string
    {
        return match (true) {
            $status === ResearchClaimStatus::Contradicted => ResearchToScriptContext::CLASSIFICATION_CONTRADICTED,
            $status === ResearchClaimStatus::Unverified,
            $status === ResearchClaimStatus::Uncertain => ResearchToScriptContext::CLASSIFICATION_REQUIRES_VERIFICATION,
            ! $hasEvidence => ResearchToScriptContext::CLASSIFICATION_UNSUPPORTED,
            default => ResearchToScriptContext::CLASSIFICATION_USABLE,
        };
    }

    /**
     * @return array{id: int, claim: string, importance: string, status: string, classification: string, has_evidence: bool, sources: array<int, array{id: int, title: string, domain: string|null, url: string, source_type: string}>}
     */
    private function claimEntry(ResearchClaim $claim): array
    {
        $sources = $claim->sources
            ->sortBy('id')
            ->values()
            ->map(fn (Source $source): array => [
                'id' => $source->id,
                'title' => $source->title,
                'domain' => $source->domain,
                'url' => $source->url,
                'source_type' => $source->source_type->value,
            ])
            ->all();

        $hasEvidence = $sources !== [];

        return [
            'id' => $claim->id,
            'claim' => $claim->claim,
            'importance' => $claim->importance->value,
            'status' => $claim->status->value,
            'classification' => $this->classify($claim->status, $hasEvidence),
            'has_evidence' => $hasEvidence,
            'sources' => $sources,
        ];
    }

    /**
     * @param  array{issues: array<int, array{code: string, severity: string, claim_id: int|null, message: string}>}  $quality
     * @return array<int, array{code: string, severity: string, claim_id: int|null, message: string}>
     */
    private function issuesBySeverity(array $quality, string $severity): array
    {
        return array_values(array_filter(
            $quality['issues'],
            fn (array $issue): bool => $issue['severity'] === $severity
        ));
    }
}
