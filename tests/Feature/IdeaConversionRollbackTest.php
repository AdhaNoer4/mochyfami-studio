<?php

namespace Tests\Feature;

use App\Enums\ContentIdeaStatus;
use App\Models\ContentCategory;
use App\Models\ContentIdea;
use App\Models\ContentProject;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IdeaConversionRollbackTest extends TestCase
{
    use DatabaseMigrations;

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
            'status' => ContentIdeaStatus::Idea,
            'created_by' => $this->user->id,
        ]);
    }

    protected function tearDown(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS block_project_insert');
        DB::statement('DROP TRIGGER IF EXISTS block_idea_converted');
        parent::tearDown();
    }

    public function test_1_rolls_back_idea_status_when_project_creation_fails(): void
    {
        DB::statement("CREATE TRIGGER block_project_insert BEFORE INSERT ON content_projects FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Project insert blocked by test trigger'");

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/ideas/{$this->idea->id}/convert-to-project");

        $response->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unexpected error during conversion.');

        $this->assertDatabaseHas('content_ideas', [
            'id' => $this->idea->id,
            'status' => 'idea',
        ]);

        $this->assertSame(0, ContentProject::where('content_idea_id', $this->idea->id)->count());
    }

    public function test_2_rolls_back_project_creation_when_idea_status_update_fails(): void
    {
        DB::statement("CREATE TRIGGER block_idea_converted BEFORE UPDATE ON content_ideas FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'content_ideas update blocked by test trigger'");

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/ideas/{$this->idea->id}/convert-to-project");

        $response->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unexpected error during conversion.');

        $this->assertDatabaseHas('content_ideas', [
            'id' => $this->idea->id,
            'status' => 'idea',
        ]);

        $this->assertSame(0, ContentProject::where('content_idea_id', $this->idea->id)->count());
    }
}
