<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ResearchStatus;
use App\Exceptions\DuplicateResearchReportException;
use App\Exceptions\InvalidResearchStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Research\CreateResearchRequest;
use App\Http\Requests\Research\UpdateResearchRequest;
use App\Http\Requests\Research\UpdateResearchStatusRequest;
use App\Http\Resources\ResearchResource;
use App\Models\ContentProject;
use App\Models\ResearchReport;
use App\Services\ResearchService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ResearchController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ResearchService $researchService
    ) {}

    /**
     * Create a research report for the project.
     */
    public function store(CreateResearchRequest $request, ContentProject $project): JsonResponse
    {
        Gate::authorize('create', ResearchReport::class);

        try {
            $report = $this->researchService->createReport($project);
        } catch (DuplicateResearchReportException $e) {
            return $this->errorResponse($e->getMessage(), null, 409);
        }

        return $this->successResponse(
            new ResearchResource($report->load(['project', 'claims', 'sources'])),
            'Research report created successfully.',
            201
        );
    }

    /**
     * Display the project research report with claims and sources.
     */
    public function show(ContentProject $project): JsonResponse
    {
        $report = $this->researchService->getReport($project);

        if (! $report) {
            return $this->successResponse(null, 'No research report yet for this project.');
        }

        return $this->successResponse(new ResearchResource($report));
    }

    /**
     * Update the research report summary and researched date.
     */
    public function update(UpdateResearchRequest $request, ContentProject $project): JsonResponse
    {
        try {
            $report = $this->researchService->updateReport($project, $request->validated());
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        return $this->successResponse(new ResearchResource($report), 'Research report updated successfully.');
    }

    /**
     * Transition the research report status.
     */
    public function transitionStatus(UpdateResearchStatusRequest $request, ContentProject $project): JsonResponse
    {
        try {
            $report = $this->researchService->getReportOrFail($project);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('updateStatus', $report);

        try {
            $updatedReport = $this->researchService->transitionStatus(
                $project,
                ResearchStatus::from($request->validated()['status'])
            );
        } catch (InvalidResearchStatusTransitionException $e) {
            return $this->errorResponse($e->getMessage(), null, 422);
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse('Unable to update research status.', null, 500);
        }

        return $this->successResponse(new ResearchResource($updatedReport), 'Research status updated successfully.');
    }

    /**
     * Delete the research report.
     */
    public function destroy(ContentProject $project): JsonResponse
    {
        try {
            $report = $this->researchService->getReportOrFail($project);
        } catch (ModelNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), null, 404);
        }

        Gate::authorize('delete', $report);

        $this->researchService->deleteReport($project);

        return $this->successResponse(null, 'Research report deleted successfully.');
    }
}
