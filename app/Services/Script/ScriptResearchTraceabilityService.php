<?php

namespace App\Services\Script;

use App\Enums\ResearchClaimStatus;
use App\Exceptions\DuplicateScriptResearchClaimException;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\Script;
use App\Models\ScriptVersion;
use App\Services\ScriptService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Maintains the script-version to research-claim traceability mapping.
 *
 * Traceability is provenance metadata, not proof of factual correctness. A
 * mapping records that a human reviewer linked a research claim to a script
 * version; it never mutates research, script content, statuses or quality.
 *
 * Every operation re-validates project/script/version/claim ownership so a
 * cross-project mapping is impossible.
 */
class ScriptResearchTraceabilityService
{
    public function __construct(
        protected ScriptService $scriptService
    ) {}

    /**
     * List research claims mapped to a script version, eager loaded with
     * their evidence sources and ordered deterministically by claim id.
     *
     * @throws ModelNotFoundException
     */
    public function listMappings(ContentProject $project, ScriptVersion $version): Collection
    {
        $script = $this->scriptService->getScriptOrFail($project);
        $this->assertVersionBelongsToScript($version, $script);

        return $version->researchClaims()
            ->with('sources')
            ->orderBy('research_claims.id')
            ->get();
    }

    /**
     * Map a research claim to a script version.
     *
     * A mapping is valid only when the version belongs to the project script
     * and the claim belongs to the same project's research report.
     *
     * @throws DuplicateScriptResearchClaimException
     * @throws ModelNotFoundException
     */
    public function attachClaim(ContentProject $project, ScriptVersion $version, ResearchClaim $claim): ResearchClaim
    {
        $script = $this->scriptService->getScriptOrFail($project);
        $this->assertVersionBelongsToScript($version, $script);
        $this->assertClaimBelongsToProjectReport($project, $claim);

        if ($version->researchClaims()->whereKey($claim->getKey())->exists()) {
            throw new DuplicateScriptResearchClaimException('This research claim is already mapped to the script version.');
        }

        $version->researchClaims()->attach($claim->getKey());

        return $claim->load('sources');
    }

    /**
     * Remove a research claim from a script version.
     *
     * Detaching is idempotent and only ever removes the pivot mapping.
     *
     * @throws ModelNotFoundException
     */
    public function detachClaim(ContentProject $project, ScriptVersion $version, ResearchClaim $claim): void
    {
        $script = $this->scriptService->getScriptOrFail($project);
        $this->assertVersionBelongsToScript($version, $script);
        $this->assertClaimBelongsToProjectReport($project, $claim);

        $version->researchClaims()->detach($claim->getKey());
    }

    /**
     * Build a deterministic traceability summary for a script version.
     *
     * traceability_complete is a boolean review signal, not a quality score:
     * it is true only when the reviewer has mapped at least one claim and
     * every mapped claim is supported. It does not prove factual correctness.
     *
     * @return array{
     *     total_claims: int,
     *     supported_claims: int,
     *     unverified_claims: int,
     *     uncertain_claims: int,
     *     contradicted_claims: int,
     *     claims_with_evidence: int,
     *     claims_without_evidence: int,
     *     traceability_complete: bool,
     *     warnings: array<int, string>
     * }
     */
    public function summary(ScriptVersion $version): array
    {
        $claims = $version->researchClaims()->with('sources')->get();

        $supported = 0;
        $unverified = 0;
        $uncertain = 0;
        $contradicted = 0;
        $withEvidence = 0;

        foreach ($claims as $claim) {
            switch ($claim->status) {
                case ResearchClaimStatus::Supported:
                    $supported++;

                    break;
                case ResearchClaimStatus::Unverified:
                    $unverified++;

                    break;
                case ResearchClaimStatus::Uncertain:
                    $uncertain++;

                    break;
                case ResearchClaimStatus::Contradicted:
                    $contradicted++;

                    break;
            }

            if ($claim->sources->isNotEmpty()) {
                $withEvidence++;
            }
        }

        $warnings = [];

        if ($claims->isEmpty()) {
            $warnings[] = 'No research claims are mapped to this script version yet.';
        }

        if ($contradicted > 0) {
            $warnings[] = "{$contradicted} mapped claim(s) are contradicted; confirm how the script handles these points.";
        }

        if ($unverified + $uncertain > 0) {
            $warnings[] = ($unverified + $uncertain).' mapped claim(s) are not verified; do not treat them as fact.';
        }

        $withoutEvidence = $claims->count() - $withEvidence;

        if ($withoutEvidence > 0) {
            $warnings[] = "{$withoutEvidence} mapped claim(s) have no supporting sources.";
        }

        $traceabilityComplete = $claims->isNotEmpty()
            && $contradicted === 0
            && $unverified === 0
            && $uncertain === 0;

        return [
            'total_claims' => $claims->count(),
            'supported_claims' => $supported,
            'unverified_claims' => $unverified,
            'uncertain_claims' => $uncertain,
            'contradicted_claims' => $contradicted,
            'claims_with_evidence' => $withEvidence,
            'claims_without_evidence' => $withoutEvidence,
            'traceability_complete' => $traceabilityComplete,
            'warnings' => $warnings,
        ];
    }

    /**
     * Ensure a script version belongs to the project script.
     *
     * @throws ModelNotFoundException
     */
    private function assertVersionBelongsToScript(ScriptVersion $version, Script $script): void
    {
        if ($version->script_id !== $script->id) {
            throw new ModelNotFoundException('Script version not found for this project.');
        }
    }

    /**
     * Ensure a research claim belongs to the project's research report.
     *
     * @throws ModelNotFoundException
     */
    private function assertClaimBelongsToProjectReport(ContentProject $project, ResearchClaim $claim): void
    {
        $report = $project->researchReport()->first();

        if (! $report || $claim->research_report_id !== $report->id) {
            throw new ModelNotFoundException('Claim not found for this project research report.');
        }
    }
}
