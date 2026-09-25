<?php

namespace App\Services;

use App\Enums\ContentIdeaStatus;
use App\Enums\ContentProjectStatus;
use App\Models\ContentIdea;
use App\Models\ContentProject;

class DashboardService
{
    /**
     * Statuses considered part of the active production workflow (overview).
     *
     * @var array<int, ContentProjectStatus>
     */
    private const ACTIVE_STATUSES = [
        ContentProjectStatus::Researching,
        ContentProjectStatus::ResearchReview,
        ContentProjectStatus::Scripting,
        ContentProjectStatus::ScriptReview,
        ContentProjectStatus::AssetCollection,
        ContentProjectStatus::Production,
        ContentProjectStatus::VideoReview,
        ContentProjectStatus::Revision,
        ContentProjectStatus::Approved,
    ];

    /**
     * Statuses included in the production queue.
     *
     * @var array<int, ContentProjectStatus>
     */
    private const QUEUE_STATUSES = [
        ContentProjectStatus::Researching,
        ContentProjectStatus::ResearchReview,
        ContentProjectStatus::Scripting,
        ContentProjectStatus::ScriptReview,
        ContentProjectStatus::AssetCollection,
        ContentProjectStatus::Production,
        ContentProjectStatus::VideoReview,
        ContentProjectStatus::Revision,
    ];

    public function dashboard(): array
    {
        return [
            'overview' => $this->overview(),
            'ideas' => $this->ideaStats(),
            'projects' => $this->projectStats(),
            'recent_ideas' => $this->recentIdeas(),
            'recent_projects' => $this->recentProjects(),
            'production_queue' => $this->productionQueue(),
        ];
    }

    /**
     * High-level counts displayed on the summary cards.
     */
    protected function overview(): array
    {
        return [
            'total_ideas' => ContentIdea::count(),
            'total_projects' => ContentProject::count(),
            'active_projects' => ContentProject::whereIn('status', self::ACTIVE_STATUSES)->count(),
            'published_projects' => ContentProject::where('status', ContentProjectStatus::Published)->count(),
        ];
    }

    /**
     * Idea distribution per existing status, with a stable zero-filled structure.
     */
    protected function ideaStats(): array
    {
        $counts = ContentIdea::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = ['total' => (int) $counts->sum()];

        foreach (ContentIdeaStatus::cases() as $status) {
            $stats[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $stats;
    }

    /**
     * Project distribution per existing status, with a stable zero-filled structure.
     */
    protected function projectStats(): array
    {
        $counts = ContentProject::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byStatus = [];

        foreach (ContentProjectStatus::cases() as $status) {
            $byStatus[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return [
            'total' => ContentProject::count(),
            'by_status' => $byStatus,
        ];
    }

    /**
     * The five most recently created ideas.
     */
    protected function recentIdeas(): array
    {
        return ContentIdea::query()
            ->with('category')
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (ContentIdea $idea) => [
                'id' => $idea->id,
                'title' => $idea->title,
                'status' => $idea->status->value,
                'status_label' => $idea->status->label(),
                'format' => $idea->format->value,
                'format_label' => $idea->format->label(),
                'category' => $idea->category?->name,
                'category_color' => $idea->category?->color ?? '#6366f1',
                'created_at' => $idea->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * The five most recently updated projects, including linked idea info.
     */
    protected function recentProjects(): array
    {
        return ContentProject::query()
            ->with('idea')
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (ContentProject $project) => $this->projectSummary($project))
            ->all();
    }

    /**
     * Active projects queued for work, oldest first.
     */
    protected function productionQueue(): array
    {
        return ContentProject::query()
            ->with('idea')
            ->whereIn('status', self::QUEUE_STATUSES)
            ->orderBy('updated_at', 'asc')
            ->limit(10)
            ->get()
            ->map(fn (ContentProject $project) => $this->projectSummary($project))
            ->all();
    }

    /**
     * Compact project shape used by recent projects and the production queue.
     */
    protected function projectSummary(ContentProject $project): array
    {
        return [
            'id' => $project->id,
            'title' => $project->title,
            'slug' => $project->slug,
            'status' => $project->status->value,
            'status_label' => $project->status->label(),
            'priority' => (int) ($project->priority ?? 2),
            'idea' => $project->idea ? [
                'id' => $project->idea->id,
                'title' => $project->idea->title,
            ] : null,
            'updated_at' => $project->updated_at?->toIso8601String(),
        ];
    }
}
