<?php

namespace App\Services;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;

class ResearchQualityService
{
    private const IMPORTANT_IMPORTANCE = ResearchClaimImportance::High;

    /**
     * Evaluate whether a research report is sufficiently verified to proceed.
     *
     * Deterministic and explainable: the gate only inspects data already
     * stored in the database (claims, sources, evidence pivot). It never
     * mutates database state and never transitions the research status.
     *
     * @return array{
     *     ready: bool,
     *     score: int,
     *     summary: array<string, int>,
     *     issues: array<int, array{code: string, severity: string, claim_id: int|null, message: string}>
     * }
     */
    public function evaluateReport(ResearchReport $report): array
    {
        $report->loadMissing('claims.sources');

        $summary = [
            'total_claims' => 0,
            'supported_claims' => 0,
            'unverified_claims' => 0,
            'uncertain_claims' => 0,
            'contradicted_claims' => 0,
            'claims_with_evidence' => 0,
            'claims_without_evidence' => 0,
            'important_claims' => 0,
            'important_claims_ready' => 0,
        ];

        $issues = [];
        $claimsReady = 0;

        foreach ($report->claims as $claim) {
            $summary['total_claims']++;

            $hasEvidence = (bool) $claim->sources->count();
            $isImportant = $claim->importance === self::IMPORTANT_IMPORTANCE;

            $summary['claims_with_evidence'] += $hasEvidence ? 1 : 0;
            $summary['claims_without_evidence'] += $hasEvidence ? 0 : 1;

            if ($isImportant) {
                $summary['important_claims']++;
            }

            $this->countStatus($summary, $claim->status);

            $claimReady = true;

            if (! $hasEvidence) {
                $claimReady = false;
                $issues[] = $this->issue('CLAIM_WITHOUT_EVIDENCE', 'blocker', $claim, 'This claim has no evidence source.');
            }

            switch ($claim->status) {
                case ResearchClaimStatus::Contradicted:
                    $claimReady = false;
                    $issues[] = $this->issue(
                        $isImportant ? 'IMPORTANT_CLAIM_CONTRADICTED' : 'CLAIM_CONTRADICTED',
                        'blocker',
                        $claim,
                        $isImportant
                            ? 'Important claim is contradicted.'
                            : 'This claim is contradicted.'
                    );
                    break;

                case ResearchClaimStatus::Uncertain:
                    if ($isImportant) {
                        $claimReady = false;
                        $issues[] = $this->issue('IMPORTANT_CLAIM_UNCERTAIN', 'blocker', $claim, 'Important claim is uncertain.');
                    } else {
                        $issues[] = $this->issue('CLAIM_UNCERTAIN', 'warning', $claim, 'This claim is uncertain.');
                    }
                    break;

                case ResearchClaimStatus::Unverified:
                    if ($isImportant) {
                        $claimReady = false;
                        $issues[] = $this->issue('IMPORTANT_CLAIM_UNVERIFIED', 'blocker', $claim, 'Important claim is not verified.');
                    } else {
                        $issues[] = $this->issue('CLAIM_UNVERIFIED', 'warning', $claim, 'This claim is not verified.');
                    }
                    break;

                case ResearchClaimStatus::Supported:
                    break;
            }

            if ($claimReady) {
                $claimsReady++;

                if ($isImportant) {
                    $summary['important_claims_ready']++;
                }
            }
        }

        if ($summary['total_claims'] === 0) {
            $issues[] = [
                'code' => 'NO_CLAIMS',
                'severity' => 'blocker',
                'claim_id' => null,
                'message' => 'The research report contains no claims.',
            ];
        }

        $hasBlocker = in_array('blocker', array_column($issues, 'severity'), true);

        return [
            'ready' => ! $hasBlocker,
            'score' => (int) round(($summary['total_claims'] > 0 ? $claimsReady / $summary['total_claims'] : 0) * 100),
            'summary' => $summary,
            'issues' => array_values($issues),
        ];
    }

    /**
     * Count the claim status once per claim in the shared summary.
     *
     * @param  array<string, int>  $summary
     */
    private function countStatus(array &$summary, ResearchClaimStatus $status): void
    {
        $summary['supported_claims'] += (int) ($status === ResearchClaimStatus::Supported);
        $summary['unverified_claims'] += (int) ($status === ResearchClaimStatus::Unverified);
        $summary['uncertain_claims'] += (int) ($status === ResearchClaimStatus::Uncertain);
        $summary['contradicted_claims'] += (int) ($status === ResearchClaimStatus::Contradicted);
    }

    /**
     * @return array{code: string, severity: string, claim_id: int|null, message: string}
     */
    private function issue(
        string $code,
        string $severity,
        ResearchClaim $claim,
        string $message
    ): array {
        return [
            'code' => $code,
            'severity' => $severity,
            'claim_id' => $claim->id,
            'message' => $message,
        ];
    }
}
