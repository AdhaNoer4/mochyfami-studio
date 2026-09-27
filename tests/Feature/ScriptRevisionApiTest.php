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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScriptRevisionApiTest extends TestCase
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

    private function scriptFor(ContentProject $project): Script
    {
        $script = Script::factory()
            ->has(ScriptVersion::factory()->state([
                'version' => 1,
                'title' => 'How Cats Purr',
                'hook' => 'Why do cats purr?',
                'body' => "Cats purr for many reasons, including contentment and self-soothing.\n",
                'closing' => 'And now you know.',
                'duration_seconds' => 45,
                'notes' => 'Record with soft background music.',
            ]), 'versions')
            ->create(['content_project_id' => $project->id]);

        $script->update(['current_version_id' => $script->versions()->first()->id]);
        $script->load('currentVersion');

        return $script;
    }

    private function revisePayload(array $overrides = []): array
    {
        return array_merge([
            'hook' => 'A sharper hook.',
        ], $overrides);
    }

    #[Test]
    public function test_revise_requires_authentication(): void
    {
        Http::preventStrayRequests();

        $this->postJson('/api/v1/projects/1/script/versions/1/revise', $this->revisePayload())
            ->assertUnauthorized();
    }

    #[Test]
    public function test_revise_returns_201_with_script_version_quality_and_traceability(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $script = $this->scriptFor($project);
        $script->update(['status' => ScriptStatus::Approved]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/1/revise", $this->revisePayload())
            ->assertCreated()
            ->assertJsonPath('message', 'Script version revised successfully.')
            ->assertJsonPath('data.script.status', ScriptStatus::Draft->value)
            ->assertJsonPath('data.script.current_version.version', 2)
            ->assertJsonPath('data.script.current_version.hook', 'A sharper hook.')
            ->assertJsonPath('data.script.current_version.closing', 'And now you know.')
            ->assertJsonPath('data.script.version_count', 2)
            ->assertJsonPath('data.version.version', 2)
            ->assertJsonPath('data.version.hook', 'A sharper hook.')
            ->assertJsonPath('data.quality.version_id', fn (int $id) => true)
            ->assertJsonPath('data.quality.version', 2)
            ->assertJsonPath('data.quality.status', ScriptStatus::Draft->value)
            ->assertJsonPath('data.traceability.total_claims', 0)
            ->assertJsonPath('data.traceability.traceability_complete', false);

        $this->assertSame($response->json('data.version.id'), $response->json('data.quality.version_id'));
        $this->assertDatabaseCount('script_versions', 2);
        $this->assertDatabaseCount('script_version_research_claim', 0);
    }

    #[Test]
    public function test_revise_inherits_omitted_editable_fields_from_the_source(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->scriptFor($project);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/1/revise", ['body' => 'A rewritten body.'])
            ->assertCreated()
            ->assertJsonPath('data.version.version', 2)
            ->assertJsonPath('data.version.body', 'A rewritten body.')
            ->assertJsonPath('data.version.hook', 'Why do cats purr?')
            ->assertJsonPath('data.version.closing', 'And now you know.');
    }

    #[Test]
    public function test_revise_returns_422_when_no_editable_field_is_provided(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->scriptFor($project);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/1/revise", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hook']);
    }

    #[Test]
    public function test_revise_returns_422_when_only_whitespace_fields_are_provided(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->scriptFor($project);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/1/revise", [
                'hook' => '   ',
                'body' => '',
                'closing' => null,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hook']);
    }

    #[Test]
    public function test_revise_returns_422_when_hook_exceeds_max_length(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->scriptFor($project);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/1/revise", [
                'hook' => str_repeat('h', 256),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hook']);
    }

    #[Test]
    public function test_revise_returns_422_when_closing_exceeds_max_length(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->scriptFor($project);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/1/revise", [
                'closing' => str_repeat('c', 256),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['closing']);
    }

    #[Test]
    public function test_revise_returns_422_when_body_is_not_a_string(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->scriptFor($project);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/1/revise", [
                'body' => ['array', 'not', 'string'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    #[Test]
    public function test_revise_returns_422_when_unknown_fields_are_provided(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->scriptFor($project);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/1/revise", [
                'hook' => 'A sharper hook.',
                'title' => 'Not editable via revision.',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);

        $this->assertSame(1, $project->script->versions()->count());
    }

    #[Test]
    public function test_revise_returns_404_when_source_version_does_not_exist(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->scriptFor($project);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/99/revise", $this->revisePayload())
            ->assertNotFound()
            ->assertJsonPath('message', 'Script version not found for this project.');
    }

    #[Test]
    public function test_revise_returns_404_when_source_version_belongs_to_another_project(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $own = $this->readyProject($user);
        $other = $this->readyProject($user);
        $this->scriptFor($own);
        $this->scriptFor($other);
        $other->script->versions()->create([
            'version' => 2,
            'hook' => 'Other project hook.',
            'body' => 'Other project body.',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$own->id}/script/versions/2/revise", $this->revisePayload())
            ->assertNotFound()
            ->assertJsonPath('message', 'Script version not found for this project.');

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$own->id}/script/versions")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function test_revise_returns_404_when_project_has_no_script(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/1/revise", $this->revisePayload())
            ->assertNotFound()
            ->assertJsonPath('message', 'Script not found for this project.');
    }

    #[Test]
    public function test_revise_keeps_previous_versions_accessible(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->scriptFor($project);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/projects/{$project->id}/script/versions/1/revise", $this->revisePayload())
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/script/versions/1")
            ->assertOk()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.hook', 'Why do cats purr?');

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/script/versions")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
