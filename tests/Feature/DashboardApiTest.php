<?php

namespace Tests\Feature;

use App\Enums\ContentFormat;
use App\Enums\ContentIdeaStatus;
use App\Enums\ContentProjectStatus;
use App\Models\ContentCategory;
use App\Models\ContentIdea;
use App\Models\ContentProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $response = $this->getJson('/api/v1/dashboard');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_receives_accurate_dashboard_metrics(): void
    {
        $user = User::factory()->create();
        $category = ContentCategory::create([
            'name' => 'Cat Behavior',
            'slug' => 'cat-behavior',
            'description' => 'Behavior topics',
            'color' => '#4f46e5',
            'is_active' => true,
        ]);

        ContentIdea::create([
            'category_id' => $category->id,
            'title' => 'Why Cats Meow',
            'slug' => 'why-cats-meow',
            'hook' => 'Ever wondered why cats meow?',
            'concept' => 'Explanation of meowing',
            'format' => ContentFormat::Educational,
            'status' => ContentIdeaStatus::Idea,
            'priority' => 1,
            'created_by' => $user->id,
        ]);

        ContentProject::create([
            'category_id' => $category->id,
            'title' => 'Cat Slow Blink Secret',
            'slug' => 'cat-slow-blink-secret',
            'status' => ContentProjectStatus::Production,
            'target_duration_seconds' => 45,
            'created_by' => $user->id,
        ]);

        ContentProject::create([
            'category_id' => $category->id,
            'title' => 'Cat Purring Science',
            'slug' => 'cat-purring-science',
            'status' => ContentProjectStatus::Published,
            'target_duration_seconds' => 50,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'ideas_count' => 1,
                    'active_projects_count' => 1,
                    'review_count' => 0,
                    'published_count' => 1,
                ],
                'message' => null,
            ])
            ->assertJsonCount(2, 'data.recent_projects')
            ->assertJsonCount(1, 'data.production_queue');
    }
}
