<?php

namespace App\Services;

use App\Enums\ContentIdeaStatus;
use App\Enums\ContentProjectStatus;
use App\Exceptions\IdeaAlreadyConvertedException;
use App\Http\Resources\IdeaResource;
use App\Http\Resources\ProjectResource;
use App\Models\ContentIdea;
use App\Models\ContentProject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class IdeaService
{
    /**
     * Allowed sort fields allowlist.
     */
    protected array $allowedSortFields = [
        'created_at',
        'updated_at',
        'title',
        'priority',
    ];

    /**
     * Get paginated ideas with validated search, filter, and sorting.
     */
    public function paginateIdeas(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = ContentIdea::query()->with(['category', 'creator', 'project']);

        // Database parameter bound search across title, hook, concept
        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('hook', 'like', "%{$search}%")
                    ->orWhere('concept', 'like', "%{$search}%");
            });
        }

        // Category filter
        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        // Format filter
        if (! empty($filters['format'])) {
            $query->where('format', $filters['format']);
        }

        // Status filter
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Priority filter
        if (isset($filters['priority']) && $filters['priority'] !== '') {
            $query->where('priority', (int) $filters['priority']);
        }

        // Sorting with strict allowlist verification
        $sortField = $filters['sort'] ?? $filters['sort_by'] ?? 'created_at';
        if (! in_array($sortField, $this->allowedSortFields, true)) {
            $sortField = 'created_at';
        }

        $sortDirection = strtolower($filters['direction'] ?? 'desc');
        if (! in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = 'desc';
        }

        $query->orderBy($sortField, $sortDirection);

        // Secondary order to ensure consistent pagination ordering
        if ($sortField !== 'id') {
            $query->orderBy('id', 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new content idea.
     */
    public function createIdea(array $data, int $userId): ContentIdea
    {
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        $data['created_by'] = $userId;

        return ContentIdea::create($data);
    }

    /**
     * Update an existing content idea.
     */
    public function updateIdea(ContentIdea $idea, array $data): ContentIdea
    {
        if (empty($data['slug']) && isset($data['title'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        $idea->update($data);

        return $idea->fresh(['category', 'creator']);
    }

    /**
     * Delete an idea.
     */
    public function deleteIdea(ContentIdea $idea): void
    {
        $idea->delete();
    }

    /**
     * Convert an eligible content idea into a content project.
     *
     * Runs inside a database transaction: either both the project creation and
     * the idea status update succeed, or neither one does.
     *
     * @return array{project: ProjectResource, idea: IdeaResource}
     *
     * @throws IdeaAlreadyConvertedException When the idea was already converted.
     * @throws InvalidArgumentException When the idea status is not eligible.
     */
    public function convertIdeaToProject(ContentIdea $idea, array $data, int $userId): array
    {
        return DB::transaction(function () use ($idea, $data, $userId): array {
            $idea->refresh();

            if ($idea->status === ContentIdeaStatus::Converted || $idea->project()->exists()) {
                throw new IdeaAlreadyConvertedException('This idea has already been converted into a project.');
            }

            if (! in_array($idea->status, [ContentIdeaStatus::Idea, ContentIdeaStatus::Selected], true)) {
                throw new InvalidArgumentException('This idea is not eligible for conversion because its current status is "'.$idea->status->value.'". Only ideas with status "idea" or "selected" can be converted.');
            }

            $project = ContentProject::create([
                'content_idea_id' => $idea->id,
                'category_id' => $idea->category_id,
                'title' => $data['title'] ?? $idea->title,
                'slug' => $this->uniqueSlug($data['title'] ?? $idea->title),
                'status' => ContentProjectStatus::Draft->value,
                'priority' => $data['priority'] ?? 2,
                'hook' => $idea->hook,
                'description' => $data['notes'] ?? null,
                'current_step' => ContentProjectStatus::Draft->value,
                'progress_percent' => 0,
                'created_by' => $userId,
            ]);

            $idea->update(['status' => ContentIdeaStatus::Converted]);

            return [
                'project' => new ProjectResource($project->load(['idea', 'category', 'creator'])),
                'idea' => new IdeaResource($idea->fresh(['category', 'creator', 'project'])),
            ];
        });
    }

    /**
     * Generate a slug unique among content projects, falling back to a suffixed variant.
     */
    protected function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 1;

        while (ContentProject::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
