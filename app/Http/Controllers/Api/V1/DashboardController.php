<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ContentProjectStatus;
use App\Http\Controllers\Controller;
use App\Models\ContentIdea;
use App\Models\ContentProject;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * Handle the incoming request for dashboard metrics.
     */
    public function __invoke(): JsonResponse
    {
        $ideasCount = ContentIdea::count();

        $activeProjectsCount = ContentProject::whereNotIn('status', [
            ContentProjectStatus::Published,
            ContentProjectStatus::Archived,
            ContentProjectStatus::Failed,
        ])->count();

        $reviewCount = ContentProject::whereIn('status', [
            ContentProjectStatus::ResearchReview,
            ContentProjectStatus::ScriptReview,
            ContentProjectStatus::VideoReview,
            ContentProjectStatus::Revision,
        ])->count();

        $publishedCount = ContentProject::where('status', ContentProjectStatus::Published)->count();

        $recentProjects = ContentProject::with('category')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (ContentProject $project) => [
                'id' => $project->id,
                'title' => $project->title,
                'slug' => $project->slug,
                'status' => $project->status->value,
                'status_label' => $project->status->label(),
                'category_name' => $project->category?->name,
                'category_color' => $project->category?->color ?? '#6366f1',
                'progress_percent' => $project->progress_percent,
                'updated_at' => $project->updated_at->toIso8601String(),
            ]);

        $productionQueue = ContentProject::with('category')
            ->whereIn('status', [
                ContentProjectStatus::AssetCollection,
                ContentProjectStatus::Production,
                ContentProjectStatus::VideoReview,
            ])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (ContentProject $project) => [
                'id' => $project->id,
                'title' => $project->title,
                'slug' => $project->slug,
                'status' => $project->status->value,
                'status_label' => $project->status->label(),
                'category_name' => $project->category?->name,
                'category_color' => $project->category?->color ?? '#6366f1',
                'progress_percent' => $project->progress_percent,
                'updated_at' => $project->updated_at->toIso8601String(),
            ]);

        return $this->successResponse([
            'ideas_count' => $ideasCount,
            'active_projects_count' => $activeProjectsCount,
            'review_count' => $reviewCount,
            'published_count' => $publishedCount,
            'recent_projects' => $recentProjects,
            'production_queue' => $productionQueue,
        ]);
    }
}
