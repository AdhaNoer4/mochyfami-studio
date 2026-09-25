<?php

namespace App\Services;

use App\Enums\ContentProjectStatus;
use App\Exceptions\InvalidProjectStatusTransitionException;
use App\Models\ContentIdea;
use App\Models\ContentProject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ProjectService
{
    /**
     * Allowed sort fields allowlist.
     */
    protected array $allowedSortFields = [
        'created_at',
        'updated_at',
        'title',
        'status',
        'progress_percent',
    ];

    /**
     * Get paginated projects with search, filter, sorting, and relationship eager loading.
     */
    public function paginateProjects(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = ContentProject::query()->with(['idea', 'category', 'creator']);

        // Search in title, hook, description, notes, or related idea title
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('hook', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('idea', function ($ideaQuery) use ($search) {
                        $ideaQuery->where('title', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by status
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by content_idea_id
        if (! empty($filters['content_idea_id'])) {
            $query->where('content_idea_id', (int) $filters['content_idea_id']);
        }

        // Filter by category_id
        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        // Sort allowlist check
        $sortField = $filters['sort'] ?? 'created_at';
        if (! in_array($sortField, $this->allowedSortFields, true)) {
            $sortField = 'created_at';
        }

        $sortDirection = strtolower($filters['direction'] ?? 'desc');
        if (! in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'desc';
        }

        $query->orderBy($sortField, $sortDirection);

        if ($sortField !== 'id') {
            $query->orderBy('id', 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new content project.
     */
    public function createProject(array $data, int $userId): ContentProject
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        // If category_id omitted, populate from linked ContentIdea
        if (empty($data['category_id']) && ! empty($data['content_idea_id'])) {
            $idea = ContentIdea::find($data['content_idea_id']);
            if ($idea) {
                $data['category_id'] = $idea->category_id;
            }
        }

        if (empty($data['status'])) {
            $data['status'] = ContentProjectStatus::Draft->value;
        }

        $data['created_by'] = $userId;

        return ContentProject::create($data);
    }

    /**
     * Update an existing content project.
     */
    public function updateProject(ContentProject $project, array $data): ContentProject
    {
        if (empty($data['slug']) && isset($data['title'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        $project->update($data);

        return $project->fresh(['idea', 'category', 'creator']);
    }

    /**
     * Transition a content project to a new status, enforcing the workflow.
     */
    public function transitionProjectStatus(ContentProject $project, ContentProjectStatus $status): ContentProject
    {
        $current = $project->status;

        if ($current === $status) {
            throw new InvalidProjectStatusTransitionException(
                "Project is already in {$current->value} status."
            );
        }

        if (! $current->canTransitionTo($status)) {
            throw new InvalidProjectStatusTransitionException(
                "Project cannot transition from {$current->value} to {$status->value}."
            );
        }

        $project->update(['status' => $status]);

        return $project->fresh(['idea', 'category', 'creator']);
    }

    /**
     * Delete a content project.
     */
    public function deleteProject(ContentProject $project): void
    {
        $project->delete();
    }
}
