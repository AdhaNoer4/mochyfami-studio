<?php

namespace Tests\Feature;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Enums\ScriptStatus;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Script;
use App\Models\ScriptVersion;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScriptApiTest extends TestCase
{
    use RefreshDatabase;

    private function readyProject(User $user): ContentProject
    {
        $project = ContentProject::factory()->for($user, 'creator')->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claims = ResearchClaim::factory()->count(2)->create([
            'research_report_id' => $report->id,
            'status' => ResearchClaimStatus::Supported,
            'importance' => ResearchClaimImportance::High,
        ]);
        foreach ($claims as $claim) {
            $claim->sources()->attach($source->id);
        }

        return $project;
    }

    private function scriptPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'How Cats Purr',
            'hook' => 'Why do cats purr?',
            'body' => "Cats purr for many reasons, including contentment and self-soothing.\n",
            'closing' => 'And now you know.',
            'duration_seconds' => 45,
            'notes' => 'Record with soft background music.',
        ], $overrides);
    }

    private function createScriptFor(ContentProject $project): Script
    {
        $script = Script::factory()
            ->has(ScriptVersion::factory()->state([
                'version' => 1,
                'title' => 'How Cats Purr',
                'hook' => 'Why do cats purr?',
                'body' => "Cats purr for many reasons, including contentment and self-soothing.\n",
            ]), 'versions')
            ->create(['content_project_id' => $project->id]);

        $script->update(['current_version_id' => $script->versions()->first()->id]);

        $script->load('currentVersion');

        return $script;
    }

    public function test_create_script_requires_authentication(): void
    {
        Http::preventStrayRequests();

        $this->postJson('/api/v1/projects/1/script', $this->scriptPayload())
            ->assertUnauthorized();
    }

    public function test_ready_research_creates_script_and_returns_201(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script", $this->scriptPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', ScriptStatus::Draft->value)
            ->assertJsonPath('data.current_version.version', 1)
            ->assertJsonPath('data.current_version.title', 'How Cats Purr')
            ->assertJsonPath('data.version_count', 1)
            ->assertJsonPath('data.allowed_transitions.0.action', 'Send to Review')
            ->assertJsonPath('data.allowed_transitions.1.action', 'Archive')
            ->assertJsonPath('data.allowed_transitions.1.destructive', true);

        $this->assertDatabaseCount('scripts', 1);
        $this->assertDatabaseCount('script_versions', 1);
        $this->assertSame($project->id, $response->json('data.project_id'));
    }

    public function test_create_script_returns_409_when_script_already_exists(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->createScriptFor($project);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script", $this->scriptPayload())
            ->assertStatus(409)
            ->assertJsonPath('message', 'This project already has a script.');

        $this->assertDatabaseCount('scripts', 1);
        $this->assertDatabaseCount('script_versions', 1);
    }

    public function test_create_script_returns_422_when_research_is_not_ready(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = ContentProject::factory()->for($user, 'creator')->create();
        ResearchReport::factory()->create(['content_project_id' => $project->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script", $this->scriptPayload())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Research must be ready before creating a script.');

        $this->assertDatabaseCount('scripts', 0);
    }

    public function test_create_script_returns_422_when_no_research_report_exists(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = ContentProject::factory()->for($user, 'creator')->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script", $this->scriptPayload())
            ->assertUnprocessable();

        $this->assertDatabaseCount('scripts', 0);
    }

    public function test_create_script_validates_required_fields(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hook', 'body']);

        $this->assertDatabaseCount('scripts', 0);
    }

    public function test_create_script_validates_duration_range(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script", $this->scriptPayload(['duration_seconds' => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duration_seconds');

        $this->assertDatabaseCount('scripts', 0);
    }

    public function test_get_script_requires_authentication(): void
    {
        Http::preventStrayRequests();

        $this->getJson('/api/v1/projects/1/script')
            ->assertUnauthorized();
    }

    public function test_get_script_returns_null_when_missing(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = ContentProject::factory()->for($user, 'creator')->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/script")
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'No script yet for this project.');
    }

    public function test_get_script_returns_script_with_current_version(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->createScriptFor($project);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/script")
            ->assertOk()
            ->assertJsonPath('data.status', ScriptStatus::Draft->value)
            ->assertJsonPath('data.current_version.version', 1)
            ->assertJsonPath('data.version_count', 1);
    }

    public function test_any_authenticated_user_can_view_another_users_script(): void
    {
        Http::preventStrayRequests();
        $owner = User::factory()->create();
        $visitor = User::factory()->create();
        $project = $this->readyProject($owner);
        $this->createScriptFor($project);

        $this->actingAs($visitor, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/script")
            ->assertOk()
            ->assertJsonPath('data.current_version.title', 'How Cats Purr');
    }

    public function test_transition_status_requires_authentication(): void
    {
        Http::preventStrayRequests();

        $this->patchJson('/api/v1/projects/1/script/status', ['status' => 'review'])
            ->assertUnauthorized();
    }

    public function test_valid_transition_updates_status(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->createScriptFor($project);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/script/status", ['status' => 'review'])
            ->assertOk()
            ->assertJsonPath('data.status', ScriptStatus::Review->value)
            ->assertJsonPath('data.allowed_transitions.0.action', 'Back to Draft')
            ->assertJsonPath('data.allowed_transitions.1.action', 'Approve')
            ->assertJsonPath('data.allowed_transitions.2.action', 'Archive')
            ->assertJsonPath('data.allowed_transitions.2.destructive', true);

        $this->assertDatabaseHas('scripts', [
            'content_project_id' => $project->id,
            'status' => ScriptStatus::Review->value,
        ]);
    }

    public function test_invalid_transition_returns_422(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->createScriptFor($project);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/script/status", ['status' => 'approved'])
            ->assertUnprocessable();

        $this->assertDatabaseHas('scripts', [
            'content_project_id' => $project->id,
            'status' => ScriptStatus::Draft->value,
        ]);
    }

    public function test_transition_status_requires_valid_status(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->createScriptFor($project);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/script/status", ['status' => 'not-a-status'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_transition_status_returns_404_when_no_script_exists(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = ContentProject::factory()->for($user, 'creator')->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/script/status", ['status' => 'review'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Script not found for this project.');
    }

    public function test_list_versions_requires_authentication(): void
    {
        Http::preventStrayRequests();

        $this->getJson('/api/v1/projects/1/script/versions')
            ->assertUnauthorized();
    }

    public function test_list_versions_returns_ordered_history(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->createScriptFor($project);
        ScriptVersion::factory()->create(['script_id' => $script->id, 'version' => 2]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/script/versions")
            ->assertOk()
            ->assertJsonPath('data.0.version', 1)
            ->assertJsonPath('data.1.version', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_list_versions_returns_404_when_no_script_exists(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = ContentProject::factory()->for($user, 'creator')->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/script/versions")
            ->assertNotFound()
            ->assertJsonPath('message', 'Script not found for this project.');
    }

    public function test_show_version_requires_authentication(): void
    {
        Http::preventStrayRequests();

        $this->getJson('/api/v1/projects/1/script/versions/1')
            ->assertUnauthorized();
    }

    public function test_show_version_returns_specific_version(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->createScriptFor($project);
        ScriptVersion::factory()->create(['script_id' => $script->id, 'version' => 2, 'title' => 'Second Title']);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/script/versions/2")
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.title', 'Second Title');
    }

    public function test_show_version_returns_404_for_missing_version(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->createScriptFor($project);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/script/versions/99")
            ->assertNotFound()
            ->assertJsonPath('message', 'Script version not found for this project.');
    }

    public function test_show_version_returns_404_when_no_script_exists(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = ContentProject::factory()->for($user, 'creator')->create();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/script/versions/1")
            ->assertNotFound();
    }

    public function test_create_version_requires_authentication(): void
    {
        Http::preventStrayRequests();

        $this->postJson('/api/v1/projects/1/script/versions', $this->scriptPayload())
            ->assertUnauthorized();
    }

    public function test_create_version_appends_and_becomes_current(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->createScriptFor($project);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions", $this->scriptPayload([
                'hook' => 'A revised hook.',
            ]))
            ->assertCreated()
            ->assertJsonPath('data.current_version.version', 2)
            ->assertJsonPath('data.current_version.hook', 'A revised hook.');

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/script")
            ->assertOk()
            ->assertJsonPath('data.current_version.version', 2)
            ->assertJsonPath('data.version_count', 2);
    }

    public function test_edit_current_version_creates_new_version_and_keeps_originals_unchanged(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->createScriptFor($project);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/script/versions/current", [
                'title' => 'How Cats Purr (Revised)',
            ])
            ->assertOk()
            ->assertJsonPath('data.current_version.version', 2)
            ->assertJsonPath('data.current_version.title', 'How Cats Purr (Revised)');

        $this->assertDatabaseHas('script_versions', [
            'script_id' => $project->script->id,
            'version' => 1,
            'title' => 'How Cats Purr',
        ]);
        $this->assertDatabaseHas('script_versions', [
            'script_id' => $project->script->id,
            'version' => 2,
            'title' => 'How Cats Purr (Revised)',
        ]);
        $versionTwo = $project->script->versions()->where('version', 2)->first();
        $this->assertDatabaseHas('scripts', [
            'content_project_id' => $project->id,
            'current_version_id' => $versionTwo->id,
        ]);
    }

    public function test_edit_current_version_returns_404_when_no_script_exists(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = ContentProject::factory()->for($user, 'creator')->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/projects/{$project->id}/script/versions/current", $this->scriptPayload())
            ->assertNotFound();
    }

    public function test_edit_current_version_rejects_non_integer_version_route(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->createScriptFor($project);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/script/versions/abc")
            ->assertNotFound();
    }
}
