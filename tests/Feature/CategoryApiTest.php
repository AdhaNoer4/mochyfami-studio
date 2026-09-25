<?php

namespace Tests\Feature;

use App\Enums\ContentFormat;
use App\Enums\ContentIdeaStatus;
use App\Models\ContentCategory;
use App\Models\ContentIdea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_list_categories_with_pagination(): void
    {
        ContentCategory::factory()->count(15)->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/categories?page=1&per_page=10');

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

    public function test_can_search_categories_by_name(): void
    {
        ContentCategory::create([
            'name' => 'Feline Body Language',
            'slug' => 'feline-body-language',
            'description' => 'Tail and ear signals',
            'color' => '#6366f1',
            'is_active' => true,
        ]);

        ContentCategory::create([
            'name' => 'Bird Species',
            'slug' => 'bird-species',
            'description' => 'Parrots and owls',
            'color' => '#10b981',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/categories?search=Feline');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Feline Body Language');
    }

    public function test_can_create_category(): void
    {
        $payload = [
            'name' => 'Cat Vocalizations',
            'slug' => 'cat-vocalizations',
            'description' => 'Meows, purrs, and trills',
            'color' => '#ec4899',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/categories', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Cat Vocalizations',
                    'slug' => 'cat-vocalizations',
                    'color' => '#ec4899',
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('content_categories', [
            'name' => 'Cat Vocalizations',
            'slug' => 'cat-vocalizations',
        ]);
    }

    public function test_cannot_create_category_with_duplicate_slug(): void
    {
        ContentCategory::create([
            'name' => 'Existing Category',
            'slug' => 'existing-category',
            'color' => '#6366f1',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/categories', [
                'name' => 'Another Category',
                'slug' => 'existing-category',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    public function test_cannot_update_category_with_duplicate_slug(): void
    {
        ContentCategory::create([
            'name' => 'First Category',
            'slug' => 'first-category',
            'color' => '#6366f1',
            'is_active' => true,
        ]);

        $second = ContentCategory::create([
            'name' => 'Second Category',
            'slug' => 'second-category',
            'color' => '#10b981',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/categories/{$second->id}", [
                'slug' => 'first-category',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    public function test_can_update_category(): void
    {
        $category = ContentCategory::create([
            'name' => 'Old Category Name',
            'slug' => 'old-category-name',
            'description' => 'Old desc',
            'color' => '#000000',
            'is_active' => true,
        ]);

        $payload = [
            'name' => 'Updated Category Name',
            'slug' => 'updated-category-name',
            'color' => '#3b82f6',
            'is_active' => false,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/categories/{$category->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $category->id,
                    'name' => 'Updated Category Name',
                    'slug' => 'updated-category-name',
                    'color' => '#3b82f6',
                    'is_active' => false,
                ],
            ]);

        $this->assertDatabaseHas('content_categories', [
            'id' => $category->id,
            'name' => 'Updated Category Name',
        ]);
    }

    public function test_can_delete_unused_category(): void
    {
        $category = ContentCategory::create([
            'name' => 'Unused Category',
            'slug' => 'unused-category',
            'color' => '#94a3b8',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('content_categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_category_with_associated_ideas(): void
    {
        $category = ContentCategory::create([
            'name' => 'Category With Ideas',
            'slug' => 'category-with-ideas',
            'color' => '#ef4444',
            'is_active' => true,
        ]);

        ContentIdea::create([
            'category_id' => $category->id,
            'title' => 'Sample Idea',
            'slug' => 'sample-idea',
            'hook' => 'Sample hook',
            'concept' => 'Sample concept',
            'format' => ContentFormat::Educational,
            'status' => ContentIdeaStatus::Idea,
            'priority' => 1,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'data' => null,
            ]);

        $this->assertDatabaseHas('content_categories', ['id' => $category->id]);
    }
}
