<?php

namespace Tests\Feature;

use App\Enums\ContentProjectStatus;
use App\Models\ContentCategory;
use App\Models\ContentIdea;
use App\Models\ContentProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ContentCategory $category;

    private ContentIdea $idea;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->category = ContentCategory::factory()->create(['name' => 'Cat Behavior']);
        $this->idea = ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'title' => 'Why Cats Meow At Humans',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_1_can_list_projects_with_pagination(): void
    {
        ContentProject::factory()->count(15)->create([
            'category_id' => $this->category->id,
            'content_idea_id' => $this->idea->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/projects?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'pagination' => [
                        'total' => 15,
                        'per_page' => 10,
                        'current_page' => 1,
                        'last_page' => 2,
                    ],
                ],
            ])
            ->assertJsonCount(10, 'data.items');
    }

    public function test_2_can_create_project(): void
    {
        $payload = [
            'title' => 'Cat Tail Body Language Project',
            'slug' => 'cat-tail-body-language-project',
            'content_idea_id' => $this->idea->id,
            'status' => 'draft',
            'target_duration_seconds' => 45,
            'language' => 'en',
            'tone' => 'informative',
            'hook' => 'What does it mean when a cat wiggles its tail?',
            'description' => 'Comprehensive breakdown of feline tail movement.',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/projects', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Cat Tail Body Language Project',
                    'status' => 'draft',
                    'content_idea_id' => $this->idea->id,
                ],
            ]);

        $this->assertDatabaseHas('content_projects', [
            'title' => 'Cat Tail Body Language Project',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_3_can_update_project(): void
    {
        $project = ContentProject::factory()->create([
            'category_id' => $this->category->id,
            'content_idea_id' => $this->idea->id,
            'status' => ContentProjectStatus::Draft,
            'created_by' => $this->user->id,
        ]);

        $payload = [
            'title' => 'Updated Project Title',
            'status' => 'scripting',
            'progress_percent' => 25,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/projects/{$project->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $project->id,
                    'title' => 'Updated Project Title',
                    'status' => 'scripting',
                    'progress_percent' => 25,
                ],
            ]);

        $this->assertDatabaseHas('content_projects', [
            'id' => $project->id,
            'title' => 'Updated Project Title',
            'status' => 'scripting',
        ]);
    }

    public function test_4_can_show_project_detail(): void
    {
        $project = ContentProject::factory()->create([
            'category_id' => $this->category->id,
            'content_idea_id' => $this->idea->id,
            'title' => 'Cat Slow Blink Secret',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $project->id,
                    'title' => 'Cat Slow Blink Secret',
                    'idea' => [
                        'id' => $this->idea->id,
                        'title' => 'Why Cats Meow At Humans',
                    ],
                ],
            ]);
    }

    public function test_5_can_delete_project(): void
    {
        $project = ContentProject::factory()->create([
            'category_id' => $this->category->id,
            'content_idea_id' => $this->idea->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('content_projects', ['id' => $project->id]);
    }

    public function test_6_can_search_projects(): void
    {
        ContentProject::factory()->create([
            'category_id' => $this->category->id,
            'content_idea_id' => $this->idea->id,
            'title' => 'Kitten Sleep Cycles',
            'created_by' => $this->user->id,
        ]);

        ContentProject::factory()->create([
            'category_id' => $this->category->id,
            'content_idea_id' => $this->idea->id,
            'title' => 'Parrot Talking Frequency',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/projects?search=Kitten');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Kitten Sleep Cycles');
    }

    public function test_7_can_filter_projects_by_status(): void
    {
        ContentProject::factory()->create([
            'category_id' => $this->category->id,
            'content_idea_id' => $this->idea->id,
            'status' => ContentProjectStatus::Researching,
            'title' => 'Researching Project',
            'created_by' => $this->user->id,
        ]);

        ContentProject::factory()->create([
            'category_id' => $this->category->id,
            'content_idea_id' => $this->idea->id,
            'status' => ContentProjectStatus::Draft,
            'title' => 'Draft Project',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/projects?status=researching');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Researching Project');
    }

    public function test_8_can_filter_by_content_idea_id(): void
    {
        $otherIdea = ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
        ]);

        ContentProject::factory()->create([
            'content_idea_id' => $this->idea->id,
            'category_id' => $this->category->id,
            'title' => 'Idea 1 Project',
            'created_by' => $this->user->id,
        ]);

        ContentProject::factory()->create([
            'content_idea_id' => $otherIdea->id,
            'category_id' => $this->category->id,
            'title' => 'Idea 2 Project',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects?content_idea_id={$this->idea->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Idea 1 Project');
    }

    public function test_9_can_sort_projects(): void
    {
        ContentProject::factory()->create([
            'category_id' => $this->category->id,
            'content_idea_id' => $this->idea->id,
            'title' => 'Alpha Project',
            'created_by' => $this->user->id,
        ]);

        ContentProject::factory()->create([
            'category_id' => $this->category->id,
            'content_idea_id' => $this->idea->id,
            'title' => 'Zeta Project',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/projects?sort=title&direction=asc');

        $response->assertStatus(200)
            ->assertJsonPath('data.items.0.title', 'Alpha Project')
            ->assertJsonPath('data.items.1.title', 'Zeta Project');
    }

    public function test_11_invalid_status_returns_422(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/projects?status=invalid_status_value');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_12_invalid_per_page_returns_422(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/projects?per_page=999');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_13_invalid_content_idea_id_returns_422(): void
    {
        $payload = [
            'title' => 'Project with Bad Idea ID',
            'slug' => 'bad-idea-id-project',
            'content_idea_id' => 99999,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/projects', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content_idea_id']);
    }

    public function test_14_unauthorized_access_returns_401(): void
    {
        $response = $this->getJson('/api/v1/projects');

        $response->assertStatus(401);
    }
}
