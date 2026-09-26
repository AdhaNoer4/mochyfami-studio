<?php

namespace App\Services;

use App\Models\ResearchReport;

class ResearchPipelineService
{
    public const STAGE_EMPTY = 'empty';

    public const STAGE_DISCOVERING = 'discovering';

    public const STAGE_COLLECTING_SOURCES = 'collecting_sources';

    public const STAGE_DRAFTING_CLAIMS = 'drafting_claims';

    public const STAGE_LINKING_EVIDENCE = 'linking_evidence';

    public const STAGE_REVIEWING = 'reviewing';

    public const STAGE_READY_FOR_SCRIPT = 'ready_for_script';

    public const ACTION_ADD_SOURCE = 'ADD_SOURCE';

    public const ACTION_ADD_CLAIM = 'ADD_CLAIM';

    public const ACTION_LINK_EVIDENCE = 'LINK_EVIDENCE';

    public const ACTION_VERIFY_CLAIMS = 'VERIFY_CLAIMS';

    public const ACTION_RESOLVE_CONTRADICTIONS = 'RESOLVE_CONTRADICTIONS';

    public const ACTION_IMPROVE_QUALITY = 'IMPROVE_QUALITY';

    private const STAGE_LABELS = [
        self::STAGE_EMPTY => 'Empty',
        self::STAGE_DISCOVERING => 'Discovering',
        self::STAGE_COLLECTING_SOURCES => 'Collecting Sources',
        self::STAGE_DRAFTING_CLAIMS => 'Drafting Claims',
        self::STAGE_LINKING_EVIDENCE => 'Linking Evidence',
        self::STAGE_REVIEWING => 'Reviewing',
        self::STAGE_READY_FOR_SCRIPT => 'Ready for Script',
    ];

    public function __construct(
        protected ResearchQualityService $qualityService
    ) {}

    /**
     * Evaluate the deterministic research execution pipeline.
     *
     * Stage, progress and next actions are derived entirely from existing
     * database state. The pipeline never mutates data and never replaces
     * the ResearchStatus workflow.
     *
     * @return array{
     *     stage: string,
     *     stage_label: string,
     *     progress: int,
     *     ready_for_script: bool,
     *     next_actions: array<int, array{code: string, priority: string, message: string}>,
     *     summary: array{
     *         total_sources: int,
     *         total_claims: int,
     *         claims_with_evidence: int,
     *         claims_without_evidence: int,
     *         unresolved_claims: int,
     *         progress_components: array{sources: int, claims: int, evidence: int, quality: int}
     *     }
     * }
     */
    public function evaluate(ResearchReport $report): array
    {
        $quality = $this->qualityService->evaluateReport($report);
        $summary = $quality['summary'];

        $totalSources = $report->sources()->count();
        $totalClaims = $summary['total_claims'];
        $claimsWithoutEvidence = $summary['claims_without_evidence'];
        $unresolvedClaims = $summary['unverified_claims'] + $summary['uncertain_claims'];

        $ready = $quality['ready'];

        $stage = $this->resolveStage($ready, $totalSources, $totalClaims, $claimsWithoutEvidence);

        $components = [
            'sources' => $totalSources > 0 ? 25 : 0,
            'claims' => $totalClaims > 0 ? 25 : 0,
            'evidence' => $totalClaims > 0
                ? (int) round(25 * ($summary['claims_with_evidence'] / $totalClaims))
                : 0,
            'quality' => $ready ? 25 : 0,
        ];

        $progress = $components['sources'] + $components['claims'] + $components['evidence'] + $components['quality'];

        return [
            'stage' => $stage,
            'stage_label' => self::STAGE_LABELS[$stage],
            'progress' => $progress,
            'ready_for_script' => $ready,
            'next_actions' => $this->resolveActions($ready, $totalSources, $totalClaims, $summary),
            'summary' => [
                'total_sources' => $totalSources,
                'total_claims' => $totalClaims,
                'claims_with_evidence' => $summary['claims_with_evidence'],
                'claims_without_evidence' => $claimsWithoutEvidence,
                'unresolved_claims' => $unresolvedClaims,
                'progress_components' => $components,
            ],
        ];
    }

    private function resolveStage(
        bool $ready,
        int $totalSources,
        int $totalClaims,
        int $claimsWithoutEvidence
    ): string {
        if ($ready) {
            return self::STAGE_READY_FOR_SCRIPT;
        }

        if ($totalSources === 0 && $totalClaims === 0) {
            return self::STAGE_EMPTY;
        }

        if ($totalSources === 0) {
            return self::STAGE_COLLECTING_SOURCES;
        }

        if ($totalClaims === 0) {
            return self::STAGE_DRAFTING_CLAIMS;
        }

        if ($claimsWithoutEvidence > 0) {
            return self::STAGE_LINKING_EVIDENCE;
        }

        return self::STAGE_REVIEWING;
    }

    /**
     * @param  array<string, int>  $qualitySummary
     * @return array<int, array{code: string, priority: string, message: string}>
     */
    private function resolveActions(
        bool $ready,
        int $totalSources,
        int $totalClaims,
        array $qualitySummary
    ): array {
        if ($ready) {
            return [];
        }

        $actions = [];

        if ($totalSources === 0) {
            $actions[] = $this->action(self::ACTION_ADD_SOURCE, 'high', 'Add at least one source.');
        }

        if ($totalClaims === 0) {
            $actions[] = $this->action(self::ACTION_ADD_CLAIM, 'high', 'Add at least one claim.');
        }

        if ($qualitySummary['claims_without_evidence'] > 0) {
            $actions[] = $this->action(self::ACTION_LINK_EVIDENCE, 'high', 'Attach evidence sources to claims.');
        }

        if ($qualitySummary['contradicted_claims'] > 0) {
            $actions[] = $this->action(
                self::ACTION_RESOLVE_CONTRADICTIONS,
                'high',
                'Resolve contradicted claims.'
            );
        }

        if ($qualitySummary['unverified_claims'] + $qualitySummary['uncertain_claims'] > 0) {
            $actions[] = $this->action(
                self::ACTION_VERIFY_CLAIMS,
                'medium',
                'Verify unverified or uncertain claims.'
            );
        }

        if ($actions === []) {
            $actions[] = $this->action(
                self::ACTION_IMPROVE_QUALITY,
                'medium',
                'Improve research quality to pass the fact-check gate.'
            );
        }

        return $actions;
    }

    /**
     * @return array{code: string, priority: string, message: string}
     */
    private function action(string $code, string $priority, string $message): array
    {
        return [
            'code' => $code,
            'priority' => $priority,
            'message' => $message,
        ];
    }
}
