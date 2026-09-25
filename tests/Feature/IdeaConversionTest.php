<?php

namespace Tests\Feature;

use App\Enums\ContentIdeaStatus;
use App\Enums\ContentProjectStatus;
use App\Models\ContentCategory;
use App\Models\ContentIdea;
use App\Models\ContentProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdeaConversionTest extends TestCase
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
            'status' => ContentIdeaStatus::Idea,
            'created_by' => $this->user->id,
        ]);
    }

    private function convertEndpoint(): string
    {
        return "/api/v1/ideas/{$this->idea->id}/convert-to-project";
    }

    public function test_1_convert_eligible_idea_creates_project_marks_idea_converted_and_returns_201(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->postJson($this->convertEndpoint(), [
            'title' => 'Custom Project Title',
            'priority' => 3,
            'notes' => 'Production notes for the shoot.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Idea converted to project successfully.')
            ->assertJsonPath('data.project.title', 'Custom Project Title')
            ->assertJsonPath('data.project.status', 'draft')
            ->assertJsonPath('data.project.priority', 3)
            ->assertJsonPath('data.project.content_idea_id', $this->idea->id)
            ->assertJsonPath('data.project.description', 'Production notes for the shoot.')
            ->assertJsonPath('data.idea.status', 'converted')
            ->assertJsonPath('data.idea.status_label', 'Converted to Project');

        $project = ContentProject::where('content_idea_id', $this->idea->id)->first();
        $this->assertNotNull($project);

        $this->assertDatabaseHas('content_projects', [
            'id' => $project->id,
            'title' => 'Custom Project Title',
            'status' => 'draft',
            'priority' => 3,
            'description' => 'Production notes for the shoot.',
            'content_idea_id' => $this->idea->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('content_ideas', [
            'id' => $this->idea->id,
            'status' => 'converted',
        ]);

        $responseProjectId = $response->json('data.project.id');
        $this->assertSame($project->id, $responseProjectId);
        $this->assertSame($responseProjectId, $response->json('data.idea.project_id'));
    }

    public function test_2_defaults_project_title_to_idea_title_when_title_omitted(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->postJson($this->convertEndpoint());

        $response->assertStatus(201)
            ->assertJsonPath('data.project.title', 'Why Cats Meow At Humans');

        $this->assertDatabaseHas('content_projects', [
            'content_idea_id' => $this->idea->id,
            'title' => 'Why Cats Meow At Humans',
        ]);
    }

    public function test_3_defaults_project_priority_to_medium_when_priority_omitted(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->postJson($this->convertEndpoint());

        $response->assertStatus(201)
            ->assertJsonPath('data.project.priority', 2);

        $this->assertDatabaseHas('content_projects', [
            'content_idea_id' => $this->idea->id,
            'priority' => 2,
        ]);
    }

    public function test_4_defaults_project_notes_to_null_when_notes_omitted(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->postJson($this->convertEndpoint());

        $response->assertStatus(201)
            ->assertJsonPath('data.project.description', null);

        $project = ContentProject::where('content_idea_id', $this->idea->id)->first();
        $this->assertNull($project->description);
    }

    public function test_5_creates_new_project_with_draft_status_and_zeroth_progress(): void
    {
        $this->actingAs($this->user, 'sanctum')->postJson($this->convertEndpoint());

        $project = ContentProject::where('content_idea_id', $this->idea->id)->first();

        $this->assertSame(ContentProjectStatus::Draft, $project->status);
        $this->assertSame('draft', $project->current_step);
        $this->assertSame(0, $project->progress_percent);
    }

    public function test_6_created_project_references_the_source_idea_id(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')->postJson($this->convertEndpoint());

        $response->assertStatus(201)
            ->assertJsonPath('data.project.content_idea_id', $this->idea->id);

        $this->assertDatabaseHas('content_projects', [
            'content_idea_id' => $this->idea->id,
        ]);
    }

    public function test_7_selected_ideas_are_eligible_for_conversion(): void
    {
        $selectedIdea = ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'status' => ContentIdeaStatus::Selected,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/ideas/{$selectedIdea->id}/convert-to-project");

        $response->assertStatus(201)
            ->assertJsonPath('data.project.content_idea_id', $selectedIdea->id)
            ->assertJsonPath('data.idea.status', 'converted');
    }

    public function test_8_returns_409_when_idea_was_already_converted(): void
    {
        $convertedIdea = ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'status' => ContentIdeaStatus::Converted,
            'created_by' => $this->user->id,
        ]);

        ContentProject::factory()->create([
            'content_idea_id' => $convertedIdea->id,
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/ideas/{$convertedIdea->id}/convert-to-project");

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This idea has already been converted into a project.');

        $this->assertSame(1, ContentProject::where('content_idea_id', $convertedIdea->id)->count());
    }

    public function test_9_returns_422_when_archived_idea_cannot_be_converted(): void
    {
        $archivedIdea = ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'status' => ContentIdeaStatus::Archived,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/ideas/{$archivedIdea->id}/convert-to-project");

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This idea is not eligible for conversion because its current status is "archived". Only ideas with status "idea" or "selected" can be converted.');

        $this->assertSame(0, ContentProject::where('content_idea_id', $archivedIdea->id)->count());
    }

    public function test_10_returns_404_when_idea_does_not_exist(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/ideas/99999/convert-to-project');

        $response->assertNotFound();
    }

    public function test_11_returns_401_when_user_is_not_authenticated(): void
    {
        $response = $this->postJson($this->convertEndpoint());

        $response->assertStatus(401);
    }

    public function test_12_returns_422_when_custom_title_exceeds_255_characters(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson($this->convertEndpoint(), ['title' => str_repeat('a', 256)]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }
}
