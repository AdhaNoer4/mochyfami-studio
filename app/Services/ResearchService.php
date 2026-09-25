<?php

namespace App\Services;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Enums\ResearchStatus;
use App\Exceptions\DuplicateEvidenceRelationException;
use App\Exceptions\DuplicateResearchReportException;
use App\Exceptions\InvalidResearchStatusTransitionException;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Source;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ResearchService
{
    /**
     * Create a research report for the project.
     *
     * @throws DuplicateResearchReportException
     */
    public function createReport(ContentProject $project): ResearchReport
    {
        if ($project->researchReport()->exists()) {
            throw new DuplicateResearchReportException('This project already has a research report.');
        }

        return $project->researchReport()->create(['status' => ResearchStatus::Pending]);
    }

    /**
     * Get the project research report with claims and sources, if present.
     */
    public function getReport(ContentProject $project): ?ResearchReport
    {
        return $project->researchReport()
            ->with(['project', 'claims.sources', 'sources'])
            ->first();
    }

    /**
     * Get the project research report or fail when missing.
     *
     * @throws ModelNotFoundException
     */
    public function getReportOrFail(ContentProject $project): ResearchReport
    {
        $report = $this->getReport($project);

        if (! $report) {
            throw new ModelNotFoundException('Research report not found for this project.');
        }

        return $report;
    }

    /**
     * Update a research report's editable fields.
     *
     * @throws ModelNotFoundException
     */
    public function updateReport(ContentProject $project, array $data): ResearchReport
    {
        $report = $this->getReportOrFail($project);
        $report->update($data);

        return $report->load(['project', 'claims', 'sources']);
    }

    /**
     * Delete a research report along with its claims and sources.
     *
     * @throws ModelNotFoundException
     */
    public function deleteReport(ContentProject $project): void
    {
        $report = $this->getReportOrFail($project);
        $report->delete();
    }

    /**
     * Transition a research report to a new status through the workflow.
     *
     * @throws InvalidResearchStatusTransitionException
     * @throws ModelNotFoundException
     */
    public function transitionStatus(ContentProject $project, ResearchStatus $target): ResearchReport
    {
        $report = $this->getReportOrFail($project);

        if (! $report->status->canTransitionTo($target)) {
            throw new InvalidResearchStatusTransitionException(
                "Cannot transition research status from '{$report->status->value}' to '{$target->value}'."
            );
        }

        $report->update(['status' => $target]);

        return $report->load(['project', 'claims', 'sources']);
    }

    /**
     * List sources for the project research report.
     *
     * @throws ModelNotFoundException
     */
    public function listSources(ContentProject $project)
    {
        return $this->getReportOrFail($project)->sources()->latest('id')->get();
    }

    /**
     * Create a source for the project research report.
     *
     * @throws ModelNotFoundException
     */
    public function createSource(ContentProject $project, array $data): Source
    {
        return $this->getReportOrFail($project)->sources()->create($data);
    }

    /**
     * Update a source that belongs to the project research report.
     *
     * @throws ModelNotFoundException
     */
    public function updateSource(ContentProject $project, Source $source, array $data): Source
    {
        $this->assertSourceBelongsToReport($project, $source);
        $source->update($data);

        return $source->fresh();
    }

    /**
     * Delete a source that belongs to the project research report.
     *
     * @throws ModelNotFoundException
     */
    public function deleteSource(ContentProject $project, Source $source): void
    {
        $this->assertSourceBelongsToReport($project, $source);
        $source->delete();
    }

    /**
     * List claims for the project research report.
     *
     * @throws ModelNotFoundException
     */
    public function listClaims(ContentProject $project)
    {
        return $this->getReportOrFail($project)->claims()->latest('id')->get();
    }

    /**
     * Create a claim for the project research report.
     *
     * @throws ModelNotFoundException
     */
    public function createClaim(ContentProject $project, array $data): ResearchClaim
    {
        $data['status'] = $data['status'] ?? ResearchClaimStatus::Unverified;
        $data['importance'] = $data['importance'] ?? ResearchClaimImportance::Medium;

        return $this->getReportOrFail($project)->claims()->create($data);
    }

    /**
     * Update a claim that belongs to the project research report.
     *
     * @throws ModelNotFoundException
     */
    public function updateClaim(ContentProject $project, ResearchClaim $claim, array $data): ResearchClaim
    {
        $this->assertClaimBelongsToReport($project, $claim);
        $claim->update($data);

        return $claim->fresh();
    }

    /**
     * Delete a claim that belongs to the project research report.
     *
     * @throws ModelNotFoundException
     */
    public function deleteClaim(ContentProject $project, ResearchClaim $claim): void
    {
        $this->assertClaimBelongsToReport($project, $claim);
        $claim->delete();
    }

    /**
     * Attach a source as evidence to a claim within the same research report.
     *
     * @throws DuplicateEvidenceRelationException
     * @throws ModelNotFoundException
     */
    public function attachSourceToClaim(
        ContentProject $project,
        ResearchClaim $claim,
        Source $source
    ): ResearchClaim {
        $this->assertClaimBelongsToReport($project, $claim);
        $this->assertSourceBelongsToReport($project, $source);

        if ($claim->sources()->whereKey($source->getKey())->exists()) {
            throw new DuplicateEvidenceRelationException('This source is already attached to the claim.');
        }

        $claim->sources()->attach($source->getKey());

        return $claim->load('sources');
    }

    /**
     * Detach a source from a claim, removing only the pivot record.
     *
     * @throws ModelNotFoundException
     */
    public function detachSourceFromClaim(
        ContentProject $project,
        ResearchClaim $claim,
        Source $source
    ): void {
        $this->assertClaimBelongsToReport($project, $claim);
        $this->assertSourceBelongsToReport($project, $source);

        $claim->sources()->detach($source->getKey());
    }

    /**
     * List the sources attached as evidence to a claim.
     *
     * @throws ModelNotFoundException
     */
    public function getClaimSources(ContentProject $project, ResearchClaim $claim)
    {
        $this->assertClaimBelongsToReport($project, $claim);

        return $claim->sources()->latest('research_claim_sources.id')->get();
    }

    /**
     * Ensure a source belongs to the project's research report.
     *
     * @throws ModelNotFoundException
     */
    private function assertSourceBelongsToReport(ContentProject $project, Source $source): void
    {
        $report = $this->getReport($project);

        if (! $report || $source->research_report_id !== $report->id) {
            throw new ModelNotFoundException('Source not found for this project research report.');
        }
    }

    /**
     * Ensure a claim belongs to the project's research report.
     *
     * @throws ModelNotFoundException
     */
    private function assertClaimBelongsToReport(ContentProject $project, ResearchClaim $claim): void
    {
        $report = $this->getReport($project);

        if (! $report || $claim->research_report_id !== $report->id) {
            throw new ModelNotFoundException('Claim not found for this project research report.');
        }
    }
}
