<?php

namespace Tests\Feature;

use App\Enums\ContentFormat;
use App\Enums\ContentIdeaStatus;
use App\Models\ContentCategory;
use App\Models\ContentIdea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdeaApiTest extends TestCase
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

    public function test_1_default_pagination_returns_correct_metadata(): void
    {
        ContentIdea::factory()->count(15)->create([
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/ideas');

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

    public function test_2_search_by_title(): void
    {
        ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'title' => 'Why Cats Scratch Furniture',
            'created_by' => $this->user->id,
        ]);

        ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'title' => 'Parrot Singing Secret',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/ideas?search=Furniture');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Why Cats Scratch Furniture');
    }

    public function test_3_search_by_hook(): void
    {
        ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'title' => 'Cat Tail Signs',
            'hook' => 'What does it mean when a cat wiggles its tail?',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/ideas?search=wiggles');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Cat Tail Signs');
    }

    public function test_4_search_by_concept(): void
    {
        ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'title' => 'Kitten Senses',
            'concept' => 'Thermal vision intuition in young felines',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/ideas?search=Thermal');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Kitten Senses');
    }

    public function test_5_category_filter(): void
    {
        $otherCat = ContentCategory::factory()->create(['name' => 'Dogs']);

        ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'title' => 'Cat Idea',
            'created_by' => $this->user->id,
        ]);

        ContentIdea::factory()->create([
            'category_id' => $otherCat->id,
            'title' => 'Dog Idea',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/ideas?category_id={$this->category->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Cat Idea');
    }

    public function test_6_format_filter(): void
    {
        ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'format' => ContentFormat::Educational,
            'created_by' => $this->user->id,
        ]);

        ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'format' => ContentFormat::FunnyFact,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/ideas?format=funny_fact');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.format', 'funny_fact');
    }

    public function test_7_status_filter(): void
    {
        ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'status' => ContentIdeaStatus::Selected,
            'created_by' => $this->user->id,
        ]);

        ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'status' => ContentIdeaStatus::Idea,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/ideas?status=selected');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.status', 'selected');
    }

    public function test_8_priority_filter(): void
    {
        ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'priority' => 3,
            'created_by' => $this->user->id,
        ]);

        ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'priority' => 1,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/ideas?priority=3');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.priority', 3);
    }

    public function test_9_multiple_filters_combined(): void
    {
        ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'format' => ContentFormat::Educational,
            'status' => ContentIdeaStatus::Idea,
            'priority' => 3,
            'title' => 'Matching Idea',
            'created_by' => $this->user->id,
        ]);

        ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'format' => ContentFormat::Educational,
            'status' => ContentIdeaStatus::Selected,
            'priority' => 1,
            'title' => 'Non-Matching Idea',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/ideas?category_id={$this->category->id}&format=educational&status=idea&priority=3");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Matching Idea');
    }

    public function test_10_sorting_ascending(): void
    {
        ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'title' => 'Alpha Idea',
            'created_by' => $this->user->id,
        ]);

        ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'title' => 'Zeta Idea',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/ideas?sort=title&direction=asc');

        $response->assertStatus(200)
            ->assertJsonPath('data.items.0.title', 'Alpha Idea')
            ->assertJsonPath('data.items.1.title', 'Zeta Idea');
    }

    public function test_11_sorting_descending(): void
    {
        ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'title' => 'Alpha Idea',
            'created_by' => $this->user->id,
        ]);

        ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'title' => 'Zeta Idea',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/ideas?sort=title&direction=desc');

        $response->assertStatus(200)
            ->assertJsonPath('data.items.0.title', 'Zeta Idea')
            ->assertJsonPath('data.items.1.title', 'Alpha Idea');
    }

    public function test_12_invalid_sort_field_returns_422(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/ideas?sort=non_existent_column');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sort']);
    }

    public function test_13_invalid_sort_direction_returns_422(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/ideas?direction=sideways');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['direction']);
    }

    public function test_14_invalid_per_page_returns_422(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/ideas?per_page=999');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_15_supported_per_page_and_crud(): void
    {
        ContentIdea::factory()->count(30)->create([
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/ideas?per_page=25');

        $response->assertStatus(200)
            ->assertJsonCount(25, 'data.items')
            ->assertJsonPath('data.pagination.per_page', 25)
            ->assertJsonPath('data.pagination.last_page', 2);
    }
}
