<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ContentProjectStatus;
use App\Exceptions\InvalidProjectStatusTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\GetProjectsRequest;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Requests\Project\UpdateProjectStatusRequest;
use App\Http\Resources\ProjectResource;
use App\Models\ContentProject;
use App\Services\ProjectService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ProjectService $projectService
    ) {}

    /**
     * Display a listing of projects with validated search, filters, and pagination.
     */
    public function index(GetProjectsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 10);

        $projects = $this->projectService->paginateProjects($validated, $perPage);

        return $this->successResponse([
            'items' => ProjectResource::collection($projects->items()),
            'pagination' => [
                'total' => $projects->total(),
                'per_page' => $projects->perPage(),
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created project.
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->projectService->createProject(
            $request->validated(),
            $request->user()->id
        );

        return $this->successResponse(
            new ProjectResource($project->load(['idea', 'category', 'creator'])),
            'Content project created successfully.',
            201
        );
    }

    /**
     * Display the specified project.
     */
    public function show(ContentProject $project): JsonResponse
    {
        $project->load(['idea', 'category', 'creator']);

        return $this->successResponse(new ProjectResource($project));
    }

    /**
     * Update the specified project.
     */
    public function update(UpdateProjectRequest $request, ContentProject $project): JsonResponse
    {
        $updatedProject = $this->projectService->updateProject($project, $request->validated());

        return $this->successResponse(
            new ProjectResource($updatedProject),
            'Content project updated successfully.'
        );
    }

    /**
     * Transition the specified project to a new status.
     */
    public function transitionStatus(UpdateProjectStatusRequest $request, ContentProject $project): JsonResponse
    {
        Gate::authorize('updateStatus', $project);

        try {
            $updatedProject = $this->projectService->transitionProjectStatus(
                $project,
                ContentProjectStatus::from($request->validated()['status'])
            );
        } catch (InvalidProjectStatusTransitionException $exception) {
            return $this->errorResponse($exception->getMessage(), null, 422);
        } catch (\Throwable $exception) {
            report($exception);

            return $this->errorResponse('Unable to update project status.', null, 500);
        }

        return $this->successResponse(
            new ProjectResource($updatedProject),
            'Project status updated successfully.'
        );
    }

    /**
     * Remove the specified project.
     */
    public function destroy(ContentProject $project): JsonResponse
    {
        $this->projectService->deleteProject($project);

        return $this->successResponse(null, 'Content project deleted successfully.');
    }
}
