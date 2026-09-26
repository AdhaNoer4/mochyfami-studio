<?php

namespace App\Services\Script;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Enums\ScriptStatus;
use App\Models\ResearchClaim;
use App\Models\Script;
use App\Models\ScriptVersion;
use App\Services\ResearchQualityService;
use Illuminate\Database\Eloquent\Collection;

class ScriptQualityService
{
    /**
     * Combined word/character heuristics.
     *
     * SCRIPT_TOO_SHORT: fewer than 40 meaningful words in the combined
     * script text, which at an Indonesian voiceover pace of roughly
     * 150 words per minute is clearly below a Shorts-length script.
     *
     * SCRIPT_TOO_LONG: duration_seconds above 90 seconds, the practical
     * ceiling for vertical short-form video. When duration is unknown the
     * fallback is more than 450 words (roughly three minutes of audio).
     */
    private const MIN_WORDS_FOR_SCRIPT = 40;

    private const MAX_DURATION_SECONDS = 90;

    private const MAX_WORDS_WITHOUT_DURATION = 450;

    /**
     * Claim alignment heuristics.
     *
     * A claim needs at least two meaningful tokens for reliable matching;
     * a "match" requires at least two tokens present in the script text
     * and at least 50% of the claim tokens found. A single matching word
     * is never treated as evidence.
     */
    private const MIN_MEANINGFUL_CLAIM_TOKENS = 2;

    private const MIN_MATCHED_CLAIM_TOKENS = 2;

    private const MIN_CLAIM_COVERAGE = 0.5;

    /**
     * Documented score weights summing to 100.
     *
     * research readiness 25, content completeness 25, structure 15,
     * important claim alignment 25, placeholder cleanliness 10.
     */
    private const SCORE_RESEARCH = 25;

    private const SCORE_CONTENT_HOOK = 8;

    private const SCORE_CONTENT_BODY = 12;

    private const SCORE_CONTENT_CLOSING = 5;

    private const SCORE_STRUCTURE = 15;

    private const SCORE_ALIGNMENT = 25;

    private const SCORE_CLEANLINESS = 10;

    /**
     * Placeholder markers detected case-insensitively. This is a keyword
     * heuristic, not natural-language interpretation.
     */
    private const PLACEHOLDER_PATTERN = '/\b(todo|tbc|tbd)\b|lorem ipsum|\[insert[^\]]*\]|\[isi[^\]]*\]|<placeholder>|\{\{[^}]*\}\}/i';

    public function __construct(
        protected ResearchQualityService $qualityService,
        protected ScriptTextNormalizer $normalizer
    ) {}

    /**
     * Evaluate the current script version against the research report using
     * deterministic read-only heuristics.
     *
     * The evaluation never mutates database state, never persists a score
     * and never calls AI or the network. It answers "does the current script
     * pass deterministic scrutiny and stay aligned with the research?", not
     * "is the script factually correct".
     *
     * @return array{
     *     ready: bool,
     *     score: int,
     *     summary: array{total_checks: int, passed_checks: int, failed_checks: int, blocker_count: int, warning_count: int, important_claims: int, aligned_important_claims: int},
     *     blockers: array<int, array{code: string, severity: string, passed: bool, message: string, details: object|array<string, mixed>}>,
     *     warnings: array<int, array{code: string, severity: string, passed: bool, message: string, details: object|array<string, mixed>}>,
     *     checks: array<int, array{code: string, severity: string, passed: bool, message: string, details: object|array<string, mixed>}>,
     *     claim_alignment: array<int, array{claim_id: int, importance: string, status: string, matched: bool, match_state: string, match_score: int, message: string}>
     * }
     */
    public function evaluateScript(Script $script): array
    {
        $script->loadMissing(['project.researchReport.claims.sources', 'currentVersion']);

        $report = $script->project?->researchReport;
        $version = $script->currentVersion;
        $hasVersion = $version !== null;

        $scriptText = $hasVersion
            ? $this->normalizer->combinedScriptText($version->hook, $version->body, $version->closing)
            : '';
        $scriptTokens = $hasVersion ? array_flip($this->normalizer->tokenize($scriptText)) : [];
        $wordCount = $hasVersion ? $this->normalizer->countWords($scriptText) : 0;
        $placeholderCount = $hasVersion ? $this->countPlaceholders($scriptText) : 0;

        $checks = [];
        $claimAlignment = [];
        $importantClaims = 0;
        $alignedImportantClaims = 0;

        $researchReady = $report !== null && $this->qualityService->evaluateReport($report)['ready'];

        $checks[] = $this->check(
            'RESEARCH_NOT_READY',
            'blocker',
            $researchReady,
            $report === null
                ? 'Research report does not exist for this project.'
                : ($researchReady
                    ? 'Research passed the quality gate.'
                    : 'Research is not ready for script production.'),
            ['research_ready' => $researchReady]
        );

        $checks[] = $this->check(
            'NO_CURRENT_VERSION',
            'blocker',
            $hasVersion,
            $hasVersion ? 'A current script version exists.' : 'Script has no current version.',
            ['version_id' => $version?->id ?? null]
        );

        $statusReviewable = in_array($script->status, [ScriptStatus::Review, ScriptStatus::Approved], true) && $hasVersion;

        $checks[] = $this->check(
            'SCRIPT_STATUS_NOT_REVIEWABLE',
            'blocker',
            $statusReviewable,
            $statusReviewable
                ? "Script status ({$script->status->value}) is reviewable."
                : "Script status ({$script->status->value}) must be review or approved for production.",
            ['status' => $script->status->value]
        );

        $score = 0;
        $score += $researchReady ? self::SCORE_RESEARCH : 0;
        $score += $statusReviewable ? self::SCORE_STRUCTURE : 0;
        $score += $placeholderCount === 0 && $hasVersion ? self::SCORE_CLEANLINESS : 0;

        if ($hasVersion) {
            $this->addContentChecks($checks, $version, $scriptText, $wordCount, $placeholderCount, $score);
        }

        if ($hasVersion && $report) {
            $this->addClaimAlignment(
                $report->claims,
                $scriptTokens,
                $checks,
                $claimAlignment,
                $importantClaims,
                $alignedImportantClaims
            );
        }

        $score += $importantClaims > 0
            ? (int) round(self::SCORE_ALIGNMENT * ($alignedImportantClaims / $importantClaims))
            : self::SCORE_ALIGNMENT;

        $score = min(100, max(0, $score));

        $blockers = array_values(array_filter(
            $checks,
            fn (array $check): bool => $check['severity'] === 'blocker' && ! $check['passed']
        ));
        $warnings = array_values(array_filter(
            $checks,
            fn (array $check): bool => $check['severity'] === 'warning' && ! $check['passed']
        ));

        $totalChecks = count($checks);
        $passedChecks = count(array_filter($checks, fn (array $check): bool => $check['passed']));

        return [
            'ready' => $blockers === [],
            'score' => $score,
            'summary' => [
                'total_checks' => $totalChecks,
                'passed_checks' => $passedChecks,
                'failed_checks' => $totalChecks - $passedChecks,
                'blocker_count' => count($blockers),
                'warning_count' => count($warnings),
                'important_claims' => $importantClaims,
                'aligned_important_claims' => $alignedImportantClaims,
            ],
            'blockers' => $blockers,
            'warnings' => $warnings,
            'checks' => $checks,
            'claim_alignment' => $claimAlignment,
        ];
    }

    /**
     * @param  array<int, array{code: string, severity: string, passed: bool, message: string, details: array<string, mixed>}>  $checks
     */
    private function addContentChecks(
        array &$checks,
        ScriptVersion $version,
        string $scriptText,
        int $wordCount,
        int $placeholderCount,
        int &$score
    ): void {
        $hasHook = trim((string) $version->hook) !== '';
        $hasBody = trim((string) $version->body) !== '';
        $hasClosing = trim((string) $version->closing) !== '';
        $meaningful = $hasHook || $hasBody || $hasClosing;

        $checks[] = $this->check(
            'EMPTY_SCRIPT',
            'blocker',
            $meaningful,
            $meaningful
                ? 'Script contains meaningful content.'
                : 'The current version is empty; there is no script content.',
            ['hook' => $hasHook, 'body' => $hasBody, 'closing' => $hasClosing]
        );

        $checks[] = $this->check(
            'MISSING_HOOK',
            'warning',
            $hasHook,
            $hasHook ? 'The hook is present.' : 'The hook is empty; add a strong opening line.',
            ['field' => 'hook']
        );

        $checks[] = $this->check(
            'MISSING_BODY',
            'warning',
            $hasBody,
            $hasBody ? 'The body is present.' : 'The body is empty; add the main script content.',
            ['field' => 'body']
        );

        $checks[] = $this->check(
            'MISSING_CLOSING',
            'warning',
            $hasClosing,
            $hasClosing
                ? 'The closing is present.'
                : 'The closing is empty; some short-form scripts end without one.',
            ['field' => 'closing']
        );

        $checks[] = $this->check(
            'PLACEHOLDER_CONTENT',
            'warning',
            $placeholderCount === 0,
            $placeholderCount === 0
                ? 'No placeholder markers detected.'
                : "Detected {$placeholderCount} placeholder marker(s) that must be replaced.",
            ['match_count' => $placeholderCount]
        );

        $tooShort = $wordCount < self::MIN_WORDS_FOR_SCRIPT;
        $tooLong = $this->isTooLong($version, $wordCount);

        $checks[] = $this->check(
            'SCRIPT_TOO_SHORT',
            'warning',
            ! $tooShort,
            $tooShort
                ? "The combined script text has only {$wordCount} words; expected at least ".self::MIN_WORDS_FOR_SCRIPT.'.'
                : 'The script has enough words.',
            ['word_count' => $wordCount, 'threshold' => self::MIN_WORDS_FOR_SCRIPT]
        );

        $checks[] = $this->check(
            'SCRIPT_TOO_LONG',
            'warning',
            ! $tooLong,
            $tooLong
                ? $this->tooLongMessage($version, $wordCount)
                : 'The script is within the expected short-form length range.',
            $this->tooLongDetails($version, $wordCount)
        );

        $score += ($hasHook ? self::SCORE_CONTENT_HOOK : 0)
            + ($hasBody ? self::SCORE_CONTENT_BODY : 0)
            + ($hasClosing ? self::SCORE_CONTENT_CLOSING : 0);
    }

    /**
     * @param  Collection<int, ResearchClaim>  $claims
     * @param  array<string, int>  $scriptTokens
     * @param  array<int, array{code: string, severity: string, passed: bool, message: string, details: array<string, mixed>}>  $checks
     * @param  array<int, array{claim_id: int, importance: string, status: string, matched: bool, match_state: string, match_score: int, message: string}>  $claimAlignment
     */
    private function addClaimAlignment(
        $claims,
        array $scriptTokens,
        array &$checks,
        array &$claimAlignment,
        int &$importantClaims,
        int &$alignedImportantClaims
    ): void {
        foreach ($claims as $claim) {
            $isImportant = $claim->importance === ResearchClaimImportance::High;
            $isSupported = $claim->status === ResearchClaimStatus::Supported;

            if ($isImportant) {
                $importantClaims++;
            }

            $alignment = $this->alignClaimText($claim, $scriptTokens);
            $matched = $alignment['match_state'] === 'supported_by_text';

            $claimAlignment[] = [
                'claim_id' => $claim->id,
                'importance' => $claim->importance->value,
                'status' => $claim->status->value,
                'matched' => $matched,
                'match_state' => $alignment['match_state'],
                'match_score' => $alignment['match_score'],
                'message' => $alignment['message'],
            ];

            if ($isImportant) {
                if (! $isSupported) {
                    $checks[] = $this->check(
                        'IMPORTANT_CLAIM_NOT_RESEARCH_READY',
                        'blocker',
                        false,
                        'An important research claim is not sufficiently verified.',
                        ['claim_id' => $claim->id, 'claim_status' => $claim->status->value]
                    );
                } elseif (! $matched) {
                    $checks[] = $this->check(
                        'IMPORTANT_CLAIM_NOT_ALIGNED',
                        'blocker',
                        false,
                        'Important claim is supported but not represented in the script text.',
                        ['claim_id' => $claim->id, 'match_state' => $alignment['match_state']]
                    );
                } else {
                    $alignedImportantClaims++;
                    $checks[] = $this->check(
                        'IMPORTANT_CLAIM_ALIGNED',
                        'info',
                        true,
                        'Claim text is represented in the current script.',
                        ['claim_id' => $claim->id, 'match_score' => $alignment['match_score']]
                    );
                }
            } elseif (! $matched) {
                $checks[] = $this->check(
                    'CLAIM_NOT_ALIGNED',
                    'warning',
                    false,
                    'Claim text was not detected in the current script.',
                    ['claim_id' => $claim->id, 'match_state' => $alignment['match_state']]
                );
            }
        }
    }

    /**
     * Deterministic textual alignment between a claim and the script.
     *
     * This is a token-overlap heuristic, not a semantic or factual check.
     *
     * @param  array<string, int>  $scriptTokens
     * @return array{match_state: string, match_score: int, message: string}
     */
    private function alignClaimText(ResearchClaim $claim, array $scriptTokens): array
    {
        $claimTokens = $this->normalizer->tokenize($claim->claim);

        if (count($claimTokens) < self::MIN_MEANINGFUL_CLAIM_TOKENS) {
            return [
                'match_state' => 'insufficient_text',
                'match_score' => 0,
                'message' => 'Claim text is too short for reliable textual matching.',
            ];
        }

        $matchedCount = count(array_intersect_key(
            array_flip($claimTokens),
            $scriptTokens
        ));

        $coverage = $matchedCount / count($claimTokens);
        $matched = $matchedCount >= self::MIN_MATCHED_CLAIM_TOKENS && $coverage >= self::MIN_CLAIM_COVERAGE;

        $matchScore = (int) round($coverage * 100);

        return $matched
            ? [
                'match_state' => 'supported_by_text',
                'match_score' => $matchScore,
                'message' => 'Claim text is represented in the current script.',
            ]
            : [
                'match_state' => 'not_detected',
                'match_score' => $matchScore,
                'message' => 'Claim text was not detected in the current script.',
            ];
    }

    private function countPlaceholders(string $scriptText): int
    {
        return preg_match_all(self::PLACEHOLDER_PATTERN, $scriptText) ?: 0;
    }

    private function isTooLong(ScriptVersion $version, int $wordCount): bool
    {
        if ($version->duration_seconds !== null) {
            return $version->duration_seconds > self::MAX_DURATION_SECONDS;
        }

        return $wordCount > self::MAX_WORDS_WITHOUT_DURATION;
    }

    private function tooLongMessage(ScriptVersion $version, int $wordCount): string
    {
        if ($version->duration_seconds !== null) {
            return "The declared duration ({$version->duration_seconds}s) exceeds the ".self::MAX_DURATION_SECONDS.'s short-form ceiling.';
        }

        return "The script has {$wordCount} words without a declared duration; expected no more than ".self::MAX_WORDS_WITHOUT_DURATION.'.';
    }

    /**
     * @return array<string, int|string>
     */
    private function tooLongDetails(ScriptVersion $version, int $wordCount): array
    {
        if ($version->duration_seconds !== null) {
            return [
                'duration_seconds' => $version->duration_seconds,
                'threshold' => self::MAX_DURATION_SECONDS,
            ];
        }

        return [
            'word_count' => $wordCount,
            'threshold' => self::MAX_WORDS_WITHOUT_DURATION,
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array{code: string, severity: string, passed: bool, message: string, details: object|array<string, mixed>}
     */
    private function check(string $code, string $severity, bool $passed, string $message, array $details): array
    {
        return [
            'code' => $code,
            'severity' => $severity,
            'passed' => $passed,
            'message' => $message,
            'details' => $details === [] ? (object) [] : $details,
        ];
    }
}
