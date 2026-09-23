<?php

namespace Tests\Feature;

use App\Enums\ContentFormat;
use App\Enums\ContentIdeaStatus;
use App\Enums\ContentProjectStatus;
use App\Models\ContentCategory;
use App\Models\ContentIdea;
use App\Models\ContentProject;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_creation_and_seeding(): void
    {
        $this->seed();

        $this->assertDatabaseHas('content_categories', [
            'slug' => 'cat-behavior',
            'name' => 'Cat Behavior',
        ]);

        $this->assertDatabaseCount('content_categories', 10);
    }

    public function test_idea_belongs_to_category_and_user(): void
    {
        $user = User::factory()->create();
        $category = ContentCategory::create([
            'name' => 'Cat Biology',
            'slug' => 'cat-biology',
            'color' => '#8B5CF6',
        ]);

        $idea = ContentIdea::create([
            'category_id' => $category->id,
            'title' => 'Test Idea',
            'slug' => 'test-idea',
            'hook' => 'Test Hook',
            'format' => ContentFormat::Educational,
            'status' => ContentIdeaStatus::Idea,
            'created_by' => $user->id,
        ]);

        $this->assertTrue($idea->category->is($category));
        $this->assertTrue($idea->creator->is($user));
        $this->assertCount(1, $category->ideas);
        $this->assertCount(1, $user->ideas);
    }

    public function test_project_belongs_to_idea_category_and_user(): void
    {
        $user = User::factory()->create();
        $category = ContentCategory::create([
            'name' => 'Cat POV',
            'slug' => 'cat-pov',
        ]);

        $idea = ContentIdea::create([
            'category_id' => $category->id,
            'title' => 'Cat POV Idea',
            'slug' => 'cat-pov-idea',
            'format' => ContentFormat::POV,
            'status' => ContentIdeaStatus::Selected,
            'created_by' => $user->id,
        ]);

        $project = ContentProject::create([
            'content_idea_id' => $idea->id,
            'category_id' => $category->id,
            'title' => 'Cat POV Project',
            'slug' => 'cat-pov-project',
            'status' => ContentProjectStatus::Draft,
            'target_duration_seconds' => 30,
            'created_by' => $user->id,
        ]);

        $this->assertTrue($project->idea->is($idea));
        $this->assertTrue($project->category->is($category));
        $this->assertTrue($project->creator->is($user));
        $this->assertTrue($idea->project->is($project));
    }

    public function test_unique_slug_constraint_for_categories(): void
    {
        ContentCategory::create([
            'name' => 'Unique Category',
            'slug' => 'unique-cat',
        ]);

        $this->expectException(QueryException::class);

        ContentCategory::create([
            'name' => 'Duplicate Category',
            'slug' => 'unique-cat',
        ]);
    }

    public function test_unique_slug_constraint_for_ideas(): void
    {
        ContentIdea::create([
            'title' => 'Idea One',
            'slug' => 'idea-slug',
            'format' => ContentFormat::Educational,
        ]);

        $this->expectException(QueryException::class);

        ContentIdea::create([
            'title' => 'Idea Two',
            'slug' => 'idea-slug',
            'format' => ContentFormat::Storytelling,
        ]);
    }

    public function test_valid_project_status_transitions(): void
    {
        $this->assertTrue(ContentProjectStatus::Draft->canTransitionTo(ContentProjectStatus::Researching));
        $this->assertTrue(ContentProjectStatus::Researching->canTransitionTo(ContentProjectStatus::ResearchReview));
        $this->assertTrue(ContentProjectStatus::VideoReview->canTransitionTo(ContentProjectStatus::Approved));
        $this->assertFalse(ContentProjectStatus::Published->canTransitionTo(ContentProjectStatus::Draft));
    }
}
