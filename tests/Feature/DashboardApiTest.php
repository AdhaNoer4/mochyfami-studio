<?php

namespace Tests\Feature;

use App\Enums\ContentIdeaStatus;
use App\Enums\ContentProjectStatus;
use App\Models\ContentCategory;
use App\Models\ContentIdea;
use App\Models\ContentProject;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ContentCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->category = ContentCategory::factory()->create(['name' => 'Cat Behavior']);
    }

    private function makeIdea(array $attributes = []): ContentIdea
    {
        return ContentIdea::factory()->create(array_merge([
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
        ], $attributes));
    }

    private function makeProject(array $attributes = []): ContentProject
    {
        return ContentProject::factory()->create(array_merge([
            'category_id' => $this->category->id,
            'content_idea_id' => null,
            'created_by' => $this->user->id,
        ], $attributes));
    }

    private function fetchDashboard(): TestResponse
    {
        return $this->actingAs($this->user, 'sanctum')->getJson('/api/v1/dashboard');
    }

    public function test_dashboard_requires_authentication_returns_401(): void
    {
        $response = $this->getJson('/api/v1/dashboard');

        $response->assertStatus(401);
    }

    public function test_dashboard_returns_zeroed_stable_structure_when_database_is_empty(): void
    {
        $response = $this->fetchDashboard();

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'overview' => [
                        'total_ideas' => 0,
                        'total_projects' => 0,
                        'active_projects' => 0,
                        'published_projects' => 0,
                    ],
                    'ideas' => [
                        'total' => 0,
                        'idea' => 0,
                        'selected' => 0,
                        'converted' => 0,
                        'archived' => 0,
                    ],
                    'projects' => [
                        'total' => 0,
                        'by_status' => [
                            'draft' => 0,
                            'researching' => 0,
                            'research_review' => 0,
                            'scripting' => 0,
                            'script_review' => 0,
                            'asset_collection' => 0,
                            'production' => 0,
                            'video_review' => 0,
                            'revision' => 0,
                            'approved' => 0,
                            'published' => 0,
                            'archived' => 0,
                            'failed' => 0,
                        ],
                    ],
                    'recent_ideas' => [],
                    'recent_projects' => [],
                    'production_queue' => [],
                ],
                'message' => null,
            ]);
    }

    public function test_dashboard_returns_total_ideas_and_total_projects(): void
    {
        $this->makeIdea();
        $this->makeIdea();

        $this->makeProject();
        $this->makeProject();
        $this->makeProject();

        $response = $this->fetchDashboard();

        $response->assertOk()
            ->assertJsonPath('data.overview.total_ideas', 2)
            ->assertJsonPath('data.overview.total_projects', 3)
            ->assertJsonPath('data.ideas.total', 2)
            ->assertJsonPath('data.projects.total', 3);
    }

    public function test_dashboard_counts_active_and_published_projects(): void
    {
        $this->makeProject(['status' => ContentProjectStatus::Researching]);
        $this->makeProject(['status' => ContentProjectStatus::Approved]);
        $this->makeProject(['status' => ContentProjectStatus::Production]);
        $this->makeProject(['status' => ContentProjectStatus::Draft]);
        $this->makeProject(['status' => ContentProjectStatus::Archived]);
        $this->makeProject(['status' => ContentProjectStatus::Failed]);

        $response = $this->fetchDashboard();

        $response->assertOk()
            ->assertJsonPath('data.overview.active_projects', 3)
            ->assertJsonPath('data.overview.published_projects', 0);
    }

    public function test_dashboard_counts_published_projects(): void
    {
        $this->makeProject(['status' => ContentProjectStatus::Published]);
        $this->makeProject(['status' => ContentProjectStatus::Published]);

        $response = $this->fetchDashboard();

        $response->assertOk()
            ->assertJsonPath('data.overview.published_projects', 2);
    }

    public function test_dashboard_counts_idea_statuses(): void
    {
        $this->makeIdea(['status' => ContentIdeaStatus::Idea]);
        $this->makeIdea(['status' => ContentIdeaStatus::Idea]);
        $this->makeIdea(['status' => ContentIdeaStatus::Selected]);
        $this->makeIdea(['status' => ContentIdeaStatus::Converted]);
        $this->makeIdea(['status' => ContentIdeaStatus::Archived]);

        $response = $this->fetchDashboard();

        $response->assertOk()
            ->assertJsonPath('data.ideas.total', 5)
            ->assertJsonPath('data.ideas.idea', 2)
            ->assertJsonPath('data.ideas.selected', 1)
            ->assertJsonPath('data.ideas.converted', 1)
            ->assertJsonPath('data.ideas.archived', 1);
    }

    public function test_dashboard_counts_project_statuses_with_zero_fill(): void
    {
        $this->makeProject(['status' => ContentProjectStatus::Draft]);
        $this->makeProject(['status' => ContentProjectStatus::Production]);
        $this->makeProject(['status' => ContentProjectStatus::Published]);

        $response = $this->fetchDashboard();

        $response->assertOk()
            ->assertJsonPath('data.projects.total', 3)
            ->assertJsonPath('data.projects.by_status.draft', 1)
            ->assertJsonPath('data.projects.by_status.production', 1)
            ->assertJsonPath('data.projects.by_status.published', 1)
            ->assertJsonPath('data.projects.by_status.researching', 0)
            ->assertJsonPath('data.projects.by_status.failed', 0)
            ->assertJsonPath('data.projects.by_status.archived', 0);
    }

    public function test_dashboard_returns_five_most_recent_ideas_ordered_by_created_at(): void
    {
        foreach (range(0, 5) as $index) {
            $idea = $this->makeIdea(['title' => "Idea {$index}"]);

            $idea->created_at = now()->subMinutes(5 - $index);
            $idea->save();
        }

        $response = $this->fetchDashboard();

        $response->assertOk()
            ->assertJsonCount(5, 'data.recent_ideas')
            ->assertJsonPath('data.recent_ideas.0.title', 'Idea 5')
            ->assertJsonPath('data.recent_ideas.4.title', 'Idea 1');
    }

    public function test_dashboard_returns_five_most_recent_projects_ordered_by_updated_at(): void
    {
        foreach (range(0, 5) as $index) {
            $project = $this->makeProject(['title' => "Project {$index}"]);

            $project->updated_at = now()->subMinutes(5 - $index);
            $project->save();
        }

        $response = $this->fetchDashboard();

        $response->assertOk()
            ->assertJsonCount(5, 'data.recent_projects')
            ->assertJsonPath('data.recent_projects.0.title', 'Project 5')
            ->assertJsonPath('data.recent_projects.4.title', 'Project 1');
    }

    public function test_dashboard_production_queue_includes_all_active_statuses(): void
    {
        $this->makeProject(['status' => ContentProjectStatus::Researching]);
        $this->makeProject(['status' => ContentProjectStatus::ResearchReview]);
        $this->makeProject(['status' => ContentProjectStatus::Scripting]);
        $this->makeProject(['status' => ContentProjectStatus::ScriptReview]);
        $this->makeProject(['status' => ContentProjectStatus::AssetCollection]);
        $this->makeProject(['status' => ContentProjectStatus::Production]);
        $this->makeProject(['status' => ContentProjectStatus::VideoReview]);
        $this->makeProject(['status' => ContentProjectStatus::Revision]);
        $this->makeProject(['status' => ContentProjectStatus::Approved]);

        $response = $this->fetchDashboard();

        $response->assertOk()
            ->assertJsonCount(8, 'data.production_queue')
            ->assertJsonMissing(['data.production_queue' => [['status' => 'approved']]]);
    }

    public function test_dashboard_production_queue_excludes_draft_published_archived_failed(): void
    {
        $this->makeProject(['status' => ContentProjectStatus::Researching]);
        $this->makeProject(['status' => ContentProjectStatus::Draft]);
        $this->makeProject(['status' => ContentProjectStatus::Published]);
        $this->makeProject(['status' => ContentProjectStatus::Archived]);
        $this->makeProject(['status' => ContentProjectStatus::Failed]);

        $response = $this->fetchDashboard();

        $response->assertOk()
            ->assertJsonCount(1, 'data.production_queue')
            ->assertJsonPath('data.production_queue.0.status', 'researching');
    }

    public function test_dashboard_production_queue_ordered_oldest_first(): void
    {
        foreach ([10, 5, 0] as $index => $minutesAgo) {
            $project = $this->makeProject([
                'status' => ContentProjectStatus::Production,
                'title' => "Queue Project {$index}",
            ]);

            $project->updated_at = now()->subMinutes($minutesAgo);
            $project->save();
        }

        $response = $this->fetchDashboard();

        $response->assertOk()
            ->assertJsonCount(3, 'data.production_queue')
            ->assertJsonPath('data.production_queue.0.title', 'Queue Project 0')
            ->assertJsonPath('data.production_queue.2.title', 'Queue Project 2');
    }

    public function test_dashboard_production_queue_limited_to_ten_items(): void
    {
        ContentProject::factory()->count(12)->create([
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
            'status' => ContentProjectStatus::Production,
        ]);

        $response = $this->fetchDashboard();

        $response->assertOk()
            ->assertJsonCount(10, 'data.production_queue');
    }

    public function test_dashboard_recent_items_include_related_category_and_idea(): void
    {
        $idea = $this->makeIdea(['title' => 'Cat Tail Language']);

        $this->makeProject([
            'content_idea_id' => $idea->id,
            'title' => 'Cat Tail Language Project',
            'priority' => 1,
        ]);

        $response = $this->fetchDashboard();

        $response->assertOk()
            ->assertJsonPath('data.recent_ideas.0.title', 'Cat Tail Language')
            ->assertJsonPath('data.recent_ideas.0.category', 'Cat Behavior')
            ->assertJsonPath('data.recent_projects.0.title', 'Cat Tail Language Project')
            ->assertJsonPath('data.recent_projects.0.idea.id', $idea->id)
            ->assertJsonPath('data.recent_projects.0.priority', 1);
    }

    public function test_dashboard_loads_relationships_without_lazy_loading(): void
    {
        $idea = $this->makeIdea();
        $this->makeProject(['content_idea_id' => $idea->id, 'status' => ContentProjectStatus::Production]);

        Model::preventLazyLoading();

        try {
            $this->fetchDashboard()->assertOk();
        } finally {
            Model::preventLazyLoading(false);
        }
    }
}
