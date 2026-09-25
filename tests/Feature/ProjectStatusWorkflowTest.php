<?php

namespace Tests\Feature;

use App\Enums\ContentProjectStatus;
use App\Models\ContentCategory;
use App\Models\ContentIdea;
use App\Models\ContentProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjectStatusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ContentCategory $category;

    private ContentIdea $idea;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->category = ContentCategory::factory()->create();
        $this->idea = ContentIdea::factory()->create([
            'category_id' => $this->category->id,
            'created_by' => $this->user->id,
        ]);
    }

    private function makeProject(ContentProjectStatus $status): ContentProject
    {
        return ContentProject::factory()->create([
            'category_id' => $this->category->id,
            'content_idea_id' => $this->idea->id,
            'status' => $status,
            'created_by' => $this->user->id,
        ]);
    }

    private function transition(ContentProject $project, ContentProjectStatus $target): TestResponse
    {
        return $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/status", ['status' => $target->value]);
    }

    public static function allowedTransitionsProvider(): array
    {
        return [
            'draft to researching' => [ContentProjectStatus::Draft, ContentProjectStatus::Researching],
            'researching to research review' => [ContentProjectStatus::Researching, ContentProjectStatus::ResearchReview],
            'research review to scripting' => [ContentProjectStatus::ResearchReview, ContentProjectStatus::Scripting],
            'scripting to script review' => [ContentProjectStatus::Scripting, ContentProjectStatus::ScriptReview],
            'script review to asset collection' => [ContentProjectStatus::ScriptReview, ContentProjectStatus::AssetCollection],
            'asset collection to production' => [ContentProjectStatus::AssetCollection, ContentProjectStatus::Production],
            'production to video review' => [ContentProjectStatus::Production, ContentProjectStatus::VideoReview],
            'video review to approved' => [ContentProjectStatus::VideoReview, ContentProjectStatus::Approved],
            'approved to published' => [ContentProjectStatus::Approved, ContentProjectStatus::Published],
            'researching to revision' => [ContentProjectStatus::Researching, ContentProjectStatus::Revision],
            'scripting to revision' => [ContentProjectStatus::Scripting, ContentProjectStatus::Revision],
            'asset collection to revision' => [ContentProjectStatus::AssetCollection, ContentProjectStatus::Revision],
            'video review to revision' => [ContentProjectStatus::VideoReview, ContentProjectStatus::Revision],
            'revision to researching' => [ContentProjectStatus::Revision, ContentProjectStatus::Researching],
            'revision to scripting' => [ContentProjectStatus::Revision, ContentProjectStatus::Scripting],
            'revision to production' => [ContentProjectStatus::Revision, ContentProjectStatus::Production],
            'revision to failed' => [ContentProjectStatus::Revision, ContentProjectStatus::Failed],
            'approved to revision' => [ContentProjectStatus::Approved, ContentProjectStatus::Revision],
            'published to archived' => [ContentProjectStatus::Published, ContentProjectStatus::Archived],
            'draft to archived' => [ContentProjectStatus::Draft, ContentProjectStatus::Archived],
            'draft to failed' => [ContentProjectStatus::Draft, ContentProjectStatus::Failed],
        ];
    }

    #[DataProvider('allowedTransitionsProvider')]
    public function test_allows_valid_status_transition(ContentProjectStatus $from, ContentProjectStatus $to): void
    {
        $project = $this->makeProject($from);

        $response = $this->transition($project, $to);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Project status updated successfully.')
            ->assertJsonPath('data.id', $project->id)
            ->assertJsonPath('data.status', $to->value);

        $this->assertDatabaseHas('content_projects', [
            'id' => $project->id,
            'status' => $to->value,
        ]);
    }

    public static function invalidTransitionsProvider(): array
    {
        return [
            'draft to published' => [ContentProjectStatus::Draft, ContentProjectStatus::Published],
            'draft to approved' => [ContentProjectStatus::Draft, ContentProjectStatus::Approved],
            'draft to scripting' => [ContentProjectStatus::Draft, ContentProjectStatus::Scripting],
            'researching to published' => [ContentProjectStatus::Researching, ContentProjectStatus::Published],
            'script review to published' => [ContentProjectStatus::ScriptReview, ContentProjectStatus::Published],
            'approved to researching' => [ContentProjectStatus::Approved, ContentProjectStatus::Researching],
            'published to researching' => [ContentProjectStatus::Published, ContentProjectStatus::Researching],
            'published to failed' => [ContentProjectStatus::Published, ContentProjectStatus::Failed],
            'archived to draft' => [ContentProjectStatus::Archived, ContentProjectStatus::Draft],
            'archived to published' => [ContentProjectStatus::Archived, ContentProjectStatus::Published],
            'failed to researching' => [ContentProjectStatus::Failed, ContentProjectStatus::Researching],
            'failed to approved' => [ContentProjectStatus::Failed, ContentProjectStatus::Approved],
            'revision to published' => [ContentProjectStatus::Revision, ContentProjectStatus::Published],
            'revision to approved' => [ContentProjectStatus::Revision, ContentProjectStatus::Approved],
            'revision to asset collection' => [ContentProjectStatus::Revision, ContentProjectStatus::AssetCollection],
        ];
    }

    #[DataProvider('invalidTransitionsProvider')]
    public function test_rejects_invalid_status_transition_with_422(ContentProjectStatus $from, ContentProjectStatus $to): void
    {
        $project = $this->makeProject($from);

        $response = $this->transition($project, $to);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', "Project cannot transition from {$from->value} to {$to->value}.");

        $this->assertDatabaseHas('content_projects', [
            'id' => $project->id,
            'status' => $from->value,
        ]);
    }

    public static function sameStatusProvider(): array
    {
        return [
            'draft to draft' => [ContentProjectStatus::Draft],
            'researching to researching' => [ContentProjectStatus::Researching],
        ];
    }

    #[DataProvider('sameStatusProvider')]
    public function test_rejects_same_status_transition_with_422(ContentProjectStatus $status): void
    {
        $project = $this->makeProject($status);

        $response = $this->transition($project, $status);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', "Project is already in {$status->value} status.");

        $this->assertDatabaseHas('content_projects', [
            'id' => $project->id,
            'status' => $status->value,
        ]);
    }

    public function test_rejects_unknown_status_value_with_422(): void
    {
        $project = $this->makeProject(ContentProjectStatus::Draft);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/status", ['status' => 'not_a_status']);

        $response->assertStatus(422)->assertJsonValidationErrors(['status']);
    }

    public function test_rejects_unauthenticated_request_with_401(): void
    {
        $project = $this->makeProject(ContentProjectStatus::Draft);

        $response = $this->patchJson("/api/v1/projects/{$project->id}/status", ['status' => 'researching']);

        $response->assertStatus(401);
    }

    public function test_draft_project_exposes_only_valid_allowed_transitions(): void
    {
        $project = $this->makeProject(ContentProjectStatus::Draft);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}");

        $response->assertOk()
            ->assertJsonCount(3, 'data.allowed_transitions')
            ->assertJsonPath('data.allowed_transitions.0.status', 'researching')
            ->assertJsonPath('data.allowed_transitions.0.action', 'Start Research')
            ->assertJsonPath('data.allowed_transitions.1.status', 'archived')
            ->assertJsonPath('data.allowed_transitions.1.action', 'Archive Project')
            ->assertJsonPath('data.allowed_transitions.1.destructive', true)
            ->assertJsonPath('data.allowed_transitions.2.status', 'failed')
            ->assertJsonPath('data.allowed_transitions.2.action', 'Mark Failed')
            ->assertJsonPath('data.allowed_transitions.2.destructive', true);
    }

    public function test_revision_project_exposes_return_stage_actions(): void
    {
        $project = $this->makeProject(ContentProjectStatus::Revision);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}");

        $response->assertOk()
            ->assertJsonCount(5, 'data.allowed_transitions')
            ->assertJsonPath('data.allowed_transitions.0.action', 'Return to Research')
            ->assertJsonPath('data.allowed_transitions.1.action', 'Return to Scripting')
            ->assertJsonPath('data.allowed_transitions.2.action', 'Return to Production');
    }
}
