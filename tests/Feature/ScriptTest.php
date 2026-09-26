<?php

namespace Tests\Feature;

use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Enums\ScriptStatus;
use App\Exceptions\DuplicateScriptException;
use App\Exceptions\InvalidScriptStatusTransitionException;
use App\Exceptions\ScriptNotReadyForCreationException;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Script;
use App\Models\ScriptVersion;
use App\Models\Source;
use App\Services\ScriptService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScriptTest extends TestCase
{
    use RefreshDatabase;

    private function researchReadyProject(): ContentProject
    {
        $project = ContentProject::factory()->create();
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

    private function researchProjectNotReady(): ContentProject
    {
        $project = ContentProject::factory()->create();
        ResearchReport::factory()->create(['content_project_id' => $project->id]);

        return $project;
    }

    private function service(): ScriptService
    {
        return app(ScriptService::class);
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

    public function test_create_script_for_ready_research_creates_script_with_initial_version(): void
    {
        $project = $this->researchReadyProject();

        $script = $this->service()->createScript($project, $this->scriptPayload());

        $this->assertTrue($script->content_project_id === $project->id);
        $this->assertSame(ScriptStatus::Draft, $script->status);
        $this->assertSame(1, $script->currentVersion->version);
        $this->assertSame(1, (int) $script->version_count);
        $this->assertSame('How Cats Purr', $script->currentVersion->title);

        $this->assertDatabaseCount('scripts', 1);
        $this->assertDatabaseCount('script_versions', 1);
        $this->assertDatabaseHas('scripts', [
            'content_project_id' => $project->id,
            'status' => ScriptStatus::Draft->value,
        ]);
    }

    public function test_create_script_rejects_duplicate(): void
    {
        $project = $this->researchReadyProject();
        $this->service()->createScript($project, $this->scriptPayload());

        $this->expectException(DuplicateScriptException::class);

        $this->service()->createScript($project, $this->scriptPayload());

        $this->assertDatabaseCount('scripts', 1);
        $this->assertDatabaseCount('script_versions', 1);
    }

    public function test_create_script_rejects_when_no_research_report_exists(): void
    {
        $project = ContentProject::factory()->create();

        $this->expectException(ScriptNotReadyForCreationException::class);
        $this->expectExceptionMessage('Research must be ready before creating a script.');

        $this->service()->createScript($project, $this->scriptPayload());

        $this->assertDatabaseCount('scripts', 0);
    }

    public function test_create_script_rejects_when_research_is_not_ready(): void
    {
        $project = $this->researchProjectNotReady();

        $this->expectException(ScriptNotReadyForCreationException::class);

        $this->service()->createScript($project, $this->scriptPayload());

        $this->assertDatabaseCount('scripts', 0);
    }

    public function test_get_script_returns_null_when_missing(): void
    {
        $this->assertNull($this->service()->getScript(ContentProject::factory()->create()));
    }

    public function test_get_script_returns_script_with_current_version(): void
    {
        $project = $this->researchReadyProject();
        $created = $this->service()->createScript($project, $this->scriptPayload());

        $script = $this->service()->getScript($project);

        $this->assertSame($created->id, $script->id);
        $this->assertSame(1, $script->currentVersion->version);
        $this->assertSame(1, (int) $script->version_count);
    }

    public function test_create_version_appends_next_version_and_becomes_current(): void
    {
        $project = $this->researchReadyProject();
        $this->service()->createScript($project, $this->scriptPayload());

        $script = $this->service()->createVersion($project, $this->scriptPayload([
            'hook' => 'A revised hook.',
        ]));

        $versions = $script->versions()->orderBy('version')->get();

        $this->assertCount(2, $versions);
        $this->assertSame(2, $script->currentVersion->version);
        $this->assertSame('A revised hook.', $script->currentVersion->hook);
        $this->assertSame('Why do cats purr?', $versions->first()->hook);
        $this->assertDatabaseCount('script_versions', 2);
    }

    public function test_create_version_sequences_incrementally(): void
    {
        $project = $this->researchReadyProject();
        $this->service()->createScript($project, $this->scriptPayload());

        $this->service()->createVersion($project, $this->scriptPayload());
        $script = $this->service()->createVersion($project, $this->scriptPayload());

        $this->assertSame(3, $script->currentVersion->version);
        $this->assertSame(3, (int) $script->version_count);
    }

    public function test_server_generates_version_number_ignoring_payload(): void
    {
        $project = $this->researchReadyProject();
        $this->service()->createScript($project, $this->scriptPayload());

        $script = $this->service()->createVersion($project, $this->scriptPayload(['version' => 99]));

        $this->assertSame(2, $script->currentVersion->version);
        $this->assertDatabaseHas('script_versions', [
            'script_id' => $script->id,
            'version' => 2,
        ]);
    }

    public function test_editing_current_version_creates_new_version_and_keeps_old_immutable(): void
    {
        $project = $this->researchReadyProject();
        $this->service()->createScript($project, $this->scriptPayload());

        $script = $this->service()->updateCurrentVersion($project, [
            'body' => "A completely rewritten body.\n",
        ]);

        $versions = $script->versions()->orderBy('version')->get();

        $this->assertSame(2, $script->currentVersion->version);
        $this->assertSame("A completely rewritten body.\n", $versions->get(1)->body);
        $this->assertSame("Cats purr for many reasons, including contentment and self-soothing.\n", $versions->get(0)->body);
        $this->assertSame('How Cats Purr', $script->currentVersion->title);
        $this->assertSame('Why do cats purr?', $script->currentVersion->hook);
    }

    public function test_get_version_returns_specific_version(): void
    {
        $project = $this->researchReadyProject();
        $this->service()->createScript($project, $this->scriptPayload());
        $this->service()->createVersion($project, $this->scriptPayload(['title' => 'Second Title']));

        $version = $this->service()->getVersion($project, 2);

        $this->assertSame(2, $version->version);
        $this->assertSame('Second Title', $version->title);
    }

    public function test_get_version_throws_for_missing_version(): void
    {
        $project = $this->researchReadyProject();
        $this->service()->createScript($project, $this->scriptPayload());

        $this->expectExceptionMessage('Script version not found for this project.');

        $this->service()->getVersion($project, 99);
    }

    public function test_get_versions_orders_ascending(): void
    {
        $project = $this->researchReadyProject();
        $this->service()->createScript($project, $this->scriptPayload());
        $this->service()->createVersion($project, $this->scriptPayload(['title' => 'Second']));
        $this->service()->createVersion($project, $this->scriptPayload(['title' => 'Third']));

        $versions = $this->service()->getVersions($project);

        $this->assertSame([1, 2, 3], $versions->pluck('version')->all());
        $this->assertSame(['How Cats Purr', 'Second', 'Third'], $versions->pluck('title')->all());
    }

    public function test_transition_status_succeeds_for_valid_transition(): void
    {
        $project = $this->researchReadyProject();
        $this->service()->createScript($project, $this->scriptPayload());

        $script = $this->service()->transitionStatus($project, ScriptStatus::Review);

        $this->assertSame(ScriptStatus::Review, $script->status);
        $this->assertDatabaseHas('scripts', [
            'content_project_id' => $project->id,
            'status' => ScriptStatus::Review->value,
        ]);
    }

    public function test_transition_status_rejects_invalid_transition(): void
    {
        $project = $this->researchReadyProject();
        $this->service()->createScript($project, $this->scriptPayload());

        $this->expectException(InvalidScriptStatusTransitionException::class);

        $this->service()->transitionStatus($project, ScriptStatus::Approved);
    }

    public function test_transition_status_rejects_same_status(): void
    {
        $project = $this->researchReadyProject();
        $this->service()->createScript($project, $this->scriptPayload());

        $this->expectException(InvalidScriptStatusTransitionException::class);

        $this->service()->transitionStatus($project, ScriptStatus::Draft);
    }

    public function test_scripts_table_enforces_unique_project(): void
    {
        $project = $this->researchReadyProject();
        $this->service()->createScript($project, $this->scriptPayload());

        $this->expectException(QueryException::class);

        Script::create([
            'content_project_id' => $project->id,
            'status' => ScriptStatus::Draft->value,
        ]);
    }

    public function test_script_versions_table_enforces_unique_version_per_script(): void
    {
        $project = $this->researchReadyProject();
        $script = $this->service()->createScript($project, $this->scriptPayload());

        $this->expectException(QueryException::class);

        ScriptVersion::create([
            'script_id' => $script->id,
            'version' => 1,
            'hook' => 'Duplicate',
            'body' => 'Duplicate body',
        ]);
    }
}
