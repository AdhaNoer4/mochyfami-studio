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
use App\Services\ScriptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScriptGenerationApiTest extends TestCase
{
    use RefreshDatabase;

    private function generateUrl(ContentProject $project): string
    {
        return "/api/v1/projects/{$project->id}/script/generate";
    }

    private function readySetup(): array
    {
        $user = User::factory()->create();
        $project = ContentProject::factory()->for($user, 'creator')->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'claim' => 'Cats purr to communicate with humans',
            'status' => ResearchClaimStatus::Supported,
            'importance' => ResearchClaimImportance::High,
        ]);
        $claim->sources()->attach($source->id);

        $service = app(ScriptService::class);
        $service->createScript($project, [
            'title' => 'Why Cats Purr',
            'hook' => 'Why do cats purr?',
            'body' => "Cats purr to communicate with humans, and it soothes them.\n",
            'closing' => 'Now you know why cats purr.',
            'duration_seconds' => 45,
            'notes' => null,
        ]);

        return [$user, $project];
    }

    private function unreadyProjectWithScript(): array
    {
        $user = User::factory()->create();
        $project = ContentProject::factory()->for($user, 'creator')->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'claim' => 'Cats purr to communicate with humans',
            'status' => ResearchClaimStatus::Contradicted,
            'importance' => ResearchClaimImportance::High,
        ]);
        $claim->sources()->attach($source->id);
        Script::factory()
            ->has(ScriptVersion::factory(), 'versions')
            ->create(['content_project_id' => $project->id, 'status' => ScriptStatus::Draft]);

        return [$user, $project];
    }

    public function test_guest_is_rejected(): void
    {
        Http::preventStrayRequests();
        [, $project] = $this->readySetup();

        $this->postJson($this->generateUrl($project), ['topic' => 'Kenapa kucing suka kardus?'])
            ->assertStatus(401);
    }

    public function test_validation_failure_returns_422(): void
    {
        [$user, $project] = $this->readySetup();

        $this->actingAs($user)
            ->postJson($this->generateUrl($project), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('topic');
    }

    public function test_invalid_duration_returns_422(): void
    {
        [$user, $project] = $this->readySetup();

        $this->actingAs($user)
            ->postJson($this->generateUrl($project), ['topic' => 'Topic', 'target_duration_seconds' => 999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('target_duration_seconds');
    }

    public function test_research_not_ready_returns_422(): void
    {
        [$user, $project] = $this->unreadyProjectWithScript();

        $this->actingAs($user)
            ->postJson($this->generateUrl($project), ['topic' => 'Kenapa kucing suka kardus?'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Research must pass the quality gate before AI script generation.');
    }

    public function test_missing_research_returns_422(): void
    {
        $user = User::factory()->create();
        $project = ContentProject::factory()->for($user, 'creator')->create();
        Script::factory()
            ->has(ScriptVersion::factory(), 'versions')
            ->create(['content_project_id' => $project->id, 'status' => ScriptStatus::Draft]);

        $this->actingAs($user)
            ->postJson($this->generateUrl($project), ['topic' => 'Kenapa kucing suka kardus?'])
            ->assertStatus(422);
    }

    public function test_unknown_provider_returns_422(): void
    {
        [$user, $project] = $this->readySetup();

        $this->actingAs($user)
            ->postJson($this->generateUrl($project), ['topic' => 'Topic', 'provider' => 'does-not-exist'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The requested script generation provider is not available.');
    }

    public function test_returns_404_when_project_has_no_script(): void
    {
        [$user] = $this->readySetup();
        $orphan = ContentProject::factory()->for($user, 'creator')->create();

        $this->actingAs($user)
            ->postJson($this->generateUrl($orphan), ['topic' => 'Kenapa kucing suka kardus?'])
            ->assertStatus(404);
    }

    public function test_successful_generation_returns_201_with_structure(): void
    {
        [$user, $project] = $this->readySetup();

        $response = $this->actingAs($user)
            ->postJson($this->generateUrl($project), ['topic' => 'Kenapa kucing suka kardus?'])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Script generated successfully.')
            ->assertJsonStructure([
                'data' => [
                    'script' => [
                        'id',
                        'project_id',
                        'status',
                        'status_label',
                        'allowed_transitions',
                        'version_count',
                    ],
                    'generated_version' => [
                        'id',
                        'script_id',
                        'version',
                        'title',
                        'hook',
                        'body',
                        'closing',
                        'duration_seconds',
                        'notes',
                        'created_at',
                        'updated_at',
                    ],
                    'generation' => [
                        'provider',
                        'model',
                        'prompt_profile',
                        'prompt_version',
                        'status',
                        'source_version_id',
                        'generated_version_id',
                    ],
                    'quality' => [
                        'script_id',
                        'version_id',
                        'version',
                        'status',
                        'ready',
                        'score',
                        'summary',
                        'blockers',
                        'warnings',
                        'checks',
                        'claim_alignment',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertSame('fake', $data['generation']['provider']);
        $this->assertSame('completed', $data['generation']['status']);
        $this->assertSame(2, $data['generated_version']['version']);
        $this->assertSame($data['generated_version']['id'], $data['generation']['generated_version_id']);
        $this->assertSame(2, $data['script']['version_count']);
        $this->assertSame($data['generated_version']['id'], $data['quality']['version_id']);
    }

    public function test_generation_never_overwrites_previous_version(): void
    {
        [$user, $project] = $this->readySetup();
        $oldVersion = $project->script()->first()->currentVersion;

        $response = $this->actingAs($user)
            ->postJson($this->generateUrl($project), ['topic' => 'Kenapa kucing suka kardus?'])
            ->assertStatus(201);

        $generatedId = $response->json('data.generated_version.id');
        $this->assertNotSame($oldVersion->id, $generatedId);

        $prev = ScriptVersion::where('script_id', $oldVersion->script_id)->orderBy('version')->first();
        $this->assertSame($oldVersion->version, $prev->version);
        $this->assertSame($oldVersion->hook, $prev->hook);
        $this->assertSame($oldVersion->body, $prev->body);
    }

    public function test_generation_preserves_script_status(): void
    {
        [$user, $project] = $this->readySetup();
        $this->assertSame(ScriptStatus::Draft->value, $project->script()->first()->status->value);

        $this->actingAs($user)
            ->postJson($this->generateUrl($project), ['topic' => 'Kenapa kucing suka kardus?'])
            ->assertStatus(201);

        $this->assertSame(ScriptStatus::Draft->value, $project->script()->first()->fresh()->status->value);
    }

    public function test_other_users_can_generate(): void
    {
        [$owner, $project] = $this->readySetup();
        $visitor = User::factory()->create();

        $this->actingAs($visitor)
            ->postJson($this->generateUrl($project), ['topic' => 'Kenapa kucing suka kardus?'])
            ->assertStatus(201);
    }

    public function test_generation_only_mutates_intended_tables(): void
    {
        [$user, $project] = $this->readySetup();

        $this->actingAs($user)
            ->postJson($this->generateUrl($project), ['topic' => 'Kenapa kucing suka kardus?'])
            ->assertStatus(201);

        $this->assertSame(1, DB::table('scripts')->count());
        $this->assertSame(2, DB::table('script_versions')->count());
        $this->assertSame(1, DB::table('ai_generations')->count());
    }

    public function test_generation_makes_no_external_http_requests(): void
    {
        Http::preventStrayRequests();
        [$user, $project] = $this->readySetup();

        $this->actingAs($user)
            ->postJson($this->generateUrl($project), ['topic' => 'Kenapa kucing suka kardus?'])
            ->assertStatus(201);
    }

    public function test_generation_uses_bounded_query_count(): void
    {
        [$user, $project] = $this->readySetup();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)
            ->postJson($this->generateUrl($project), ['topic' => 'Kenapa kucing suka kardus?'])
            ->assertStatus(201);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(26, $queryCount);
    }
}
