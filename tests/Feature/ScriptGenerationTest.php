<?php

namespace Tests\Feature;

use App\Enums\AiGenerationStatus;
use App\Enums\ResearchClaimImportance;
use App\Enums\ResearchClaimStatus;
use App\Enums\ScriptStatus;
use App\Exceptions\ScriptGenerationNotReadyException;
use App\Exceptions\ScriptGenerationProviderException;
use App\Models\AiGeneration;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Script;
use App\Models\ScriptVersion;
use App\Models\Source;
use App\Services\AI\Contracts\ScriptGenerationProvider;
use App\Services\AI\DTO\ScriptGenerationRequest;
use App\Services\AI\DTO\ScriptGenerationResponse;
use App\Services\AI\Prompt\MochyFamiScriptProfile;
use App\Services\AI\ScriptGenerationProviderRegistry;
use App\Services\Script\ScriptGenerationService;
use App\Services\ScriptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScriptGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function generationService(): ScriptGenerationService
    {
        return app(ScriptGenerationService::class);
    }

    private function scriptService(): ScriptService
    {
        return app(ScriptService::class);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Why Cats Purr',
            'hook' => 'Why do cats purr?',
            'body' => "Cats purr to communicate with humans, and it soothes them.\n",
            'closing' => 'Now you know why cats purr.',
            'duration_seconds' => 45,
            'notes' => null,
        ], $overrides);
    }

    /**
     * Create a project whose research is ready and a Draft script v1.
     *
     * @return array{0: ContentProject, 1: Script, 2: ScriptVersion}
     */
    private function readyProjectWithScript(): array
    {
        $project = ContentProject::factory()->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'claim' => 'Cats purr to communicate with humans',
            'status' => ResearchClaimStatus::Supported,
            'importance' => ResearchClaimImportance::High,
        ]);
        $claim->sources()->attach($source->id);

        $script = $this->scriptService()->createScript($project, $this->payload());
        $version = $script->currentVersion;

        return [$project, $script, $version];
    }

    private function projectWithScriptWithoutResearch(): ContentProject
    {
        $project = ContentProject::factory()->create();
        Script::factory()
            ->has(ScriptVersion::factory()->state([
                'hook' => 'Why do cats purr?',
                'body' => "Cats purr to communicate with humans.\n",
                'closing' => 'Now you know.',
            ]), 'versions')
            ->create(['content_project_id' => $project->id, 'status' => ScriptStatus::Draft]);
        $project->script()->first()->update(['current_version_id' => $project->script()->first()->versions()->first()->id]);

        return $project;
    }

    private function projectWithUnreadyResearch(): ContentProject
    {
        $project = ContentProject::factory()->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'claim' => 'Cats purr to communicate with humans',
            'status' => ResearchClaimStatus::Contradicted,
            'importance' => ResearchClaimImportance::High,
        ]);
        $claim->sources()->attach($source->id);

        return $project;
    }

    private function installRegistry(array $providers): ScriptGenerationProviderRegistry
    {
        $registry = new ScriptGenerationProviderRegistry($providers);

        app()->instance(ScriptGenerationProviderRegistry::class, $registry);

        return $registry;
    }

    public function test_missing_research_throws_not_ready(): void
    {
        $project = ContentProject::factory()->create();
        Script::factory()
            ->has(ScriptVersion::factory()->state([
                'hook' => 'Hook.',
                'body' => "Body.\n",
                'closing' => 'Closing.',
            ]), 'versions')
            ->create(['content_project_id' => $project->id, 'status' => ScriptStatus::Draft]);

        $this->expectException(ScriptGenerationNotReadyException::class);
        $this->generationService()->generate($project, ['topic' => 'Kenapa kucing suka kardus?']);
    }

    public function test_research_not_ready_throws_not_ready(): void
    {
        $project = ContentProject::factory()->create();
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

        $this->expectException(ScriptGenerationNotReadyException::class);
        $this->generationService()->generate($project, ['topic' => 'Kenapa kucing suka kardus?']);
    }

    public function test_generation_succeeds_and_preserves_previous_version(): void
    {
        [$project, $script, $version] = $this->readyProjectWithScript();

        $result = $this->generationService()->generate($project, ['topic' => 'Kenapa kucing suka kardus?']);

        $generatedVersion = $result['generated_version'];
        $this->assertSame(2, $generatedVersion->version);
        $this->assertSame($generatedVersion->id, $result['script']->currentVersion->id);

        $previous = $script->fresh()->versions()->orderBy('version')->get();
        $this->assertCount(2, $previous);
        $old = $previous->first();
        $this->assertSame(1, $old->version);
        $this->assertSame($version->hook, $old->hook);
        $this->assertSame($version->body, $old->body);
    }

    public function test_generation_never_changes_script_status(): void
    {
        [$project, $script] = $this->readyProjectWithScript();
        $this->assertSame(ScriptStatus::Draft, $script->status);

        $this->generationService()->generate($project, ['topic' => 'Kenapa kucing suka kardus?']);

        $this->assertSame(ScriptStatus::Draft, $script->fresh()->status);

        $this->scriptService()->transitionStatus($project, ScriptStatus::Review);

        $this->generationService()->generate($project, ['topic' => 'Kenapa kucing suka kardus lagi?']);

        $this->assertSame(ScriptStatus::Review, $script->fresh()->status);
    }

    public function test_generation_returns_generation_metadata(): void
    {
        [$project, $script, $version] = $this->readyProjectWithScript();

        $result = $this->generationService()->generate($project, ['topic' => 'Kenapa kucing suka kardus?']);

        $this->assertSame('fake', $result['generation']['provider']);
        $this->assertNull($result['generation']['model']);
        $this->assertSame(MochyFamiScriptProfile::PROFILE, $result['generation']['prompt_profile']);
        $this->assertSame(MochyFamiScriptProfile::VERSION, $result['generation']['prompt_version']);
        $this->assertSame(AiGenerationStatus::Completed->value, $result['generation']['status']);
        $this->assertSame($version->id, $result['generation']['source_version_id']);
        $this->assertSame($result['generated_version']->id, $result['generation']['generated_version_id']);
        $this->assertSame(2, $result['script']->version_count);
    }

    public function test_quality_is_evaluated_for_generated_version(): void
    {
        [$project] = $this->readyProjectWithScript();

        $result = $this->generationService()->generate($project, ['topic' => 'Kenapa kucing suka kardus?']);

        $versionCheck = collect($result['quality']['checks'])->firstWhere('code', 'NO_CURRENT_VERSION');
        $this->assertSame($result['generated_version']->id, $versionCheck['details']['version_id'], 'Quality evaluates the generated version.');
        $this->assertNotEmpty($result['quality']['checks']);
        $this->assertFalse($result['quality']['ready'], 'Generation must never auto-approve a Draft script.');
    }

    public function test_quality_ready_once_script_is_in_review(): void
    {
        [$project, $script] = $this->readyProjectWithScript();
        $this->scriptService()->transitionStatus($project, ScriptStatus::Review);

        $result = $this->generationService()->generate($project, ['topic' => 'Kenapa kucing suka kardus?']);

        $versionCheck = collect($result['quality']['checks'])->firstWhere('code', 'NO_CURRENT_VERSION');
        $this->assertSame(ScriptStatus::Review, $script->fresh()->status);
        $this->assertTrue($result['quality']['ready']);
        $this->assertSame(100, $result['quality']['score']);
        $this->assertSame($result['generated_version']->id, $versionCheck['details']['version_id']);
    }

    public function test_provider_failure_does_not_create_version(): void
    {
        $project = $this->projectWithScriptWithoutResearch();
        // place a ready report so we reach the provider stage
        $this->makeReadyReport($project);

        $failing = new class implements ScriptGenerationProvider
        {
            public function name(): string
            {
                return 'boom';
            }

            public function generate(ScriptGenerationRequest $request): ScriptGenerationResponse
            {
                throw new ScriptGenerationProviderException(
                    'Provider exploded.',
                    ScriptGenerationProviderException::UNAVAILABLE
                );
            }
        };
        $this->installRegistry(['boom' => $failing]);

        try {
            $this->generationService()->generate($project, ['provider' => 'boom', 'topic' => 'Kenapa kucing suka kardus?']);
            $this->fail('Expected ScriptGenerationProviderException.');
        } catch (ScriptGenerationProviderException $e) {
            $this->assertSame(ScriptGenerationProviderException::UNAVAILABLE, $e->getCode());
        }

        $script = $project->script()->first();
        $this->assertSame(1, $script->versions()->count());
        $this->assertSame($script->versions()->first()->id, $script->fresh()->current_version_id);
        $this->assertSame(ScriptStatus::Draft, $script->fresh()->status);
    }

    public function test_failure_is_recorded_without_generated_version(): void
    {
        $project = ContentProject::factory()->create();
        $this->makeReadyReport($project);
        $this->scriptService()->createScript($project, $this->payload());

        $failing = new class implements ScriptGenerationProvider
        {
            public function name(): string
            {
                return 'boom';
            }

            public function generate(ScriptGenerationRequest $request): ScriptGenerationResponse
            {
                throw new ScriptGenerationProviderException(
                    'Provider exploded.',
                    ScriptGenerationProviderException::UNAVAILABLE
                );
            }
        };
        $this->installRegistry(['boom' => $failing]);

        try {
            $this->generationService()->generate($project, ['provider' => 'boom', 'topic' => 'Topic']);
            $this->fail('Expected ScriptGenerationProviderException.');
        } catch (ScriptGenerationProviderException $e) {
            $this->assertSame(ScriptGenerationProviderException::UNAVAILABLE, $e->getCode());
        }

        $record = AiGeneration::first();
        $this->assertNotNull($record);
        $this->assertSame('boom', $record->provider);
        $this->assertSame(AiGenerationStatus::Failed, $record->status);
        $this->assertNull($record->generated_version_id);
        $this->assertNotNull($record->source_version_id);
    }

    public function test_invalid_generated_output_is_rejected(): void
    {
        $project = ContentProject::factory()->create();
        $this->makeReadyReport($project);
        $this->scriptService()->createScript($project, $this->payload());

        $broken = new class implements ScriptGenerationProvider
        {
            public function name(): string
            {
                return 'broken';
            }

            public function generate(ScriptGenerationRequest $request): ScriptGenerationResponse
            {
                return new ScriptGenerationResponse(provider: 'broken', hook: '', body: '');
            }
        };
        $this->installRegistry(['broken' => $broken]);

        try {
            $this->generationService()->generate($project, ['provider' => 'broken', 'topic' => 'Topic']);
            $this->fail('Expected ScriptGenerationProviderException.');
        } catch (ScriptGenerationProviderException $e) {
            $this->assertSame(ScriptGenerationProviderException::INVALID_RESPONSE, $e->getCode());
        }

        $script = $project->script()->first();
        $this->assertSame(1, $script->versions()->count());
        $record = AiGeneration::first();
        $this->assertNotNull($record);
        $this->assertSame(AiGenerationStatus::Failed, $record->status);
        $this->assertNull($record->generated_version_id);
    }

    public function test_generation_is_deterministic(): void
    {
        [$project] = $this->readyProjectWithScript();

        $first = $this->generationService()->generate($project, ['topic' => 'Kenapa kucing suka kardus?']);
        $second = $this->generationService()->generate($project, ['topic' => 'Kenapa kucing suka kardus?']);

        $fields = ['title', 'hook', 'body', 'closing', 'duration_seconds', 'notes'];
        foreach ($fields as $field) {
            $this->assertSame($first['generated_version']->$field, $second['generated_version']->$field);
        }
    }

    public function test_successful_generation_is_audited(): void
    {
        [$project] = $this->readyProjectWithScript();

        $result = $this->generationService()->generate($project, ['topic' => 'Kenapa kucing suka kardus?']);

        $record = AiGeneration::first();
        $this->assertSame('fake', $record->provider);
        $this->assertNull($record->model);
        $this->assertSame(MochyFamiScriptProfile::PROFILE, $record->prompt_profile);
        $this->assertSame(MochyFamiScriptProfile::VERSION, $record->prompt_version);
        $this->assertSame($result['generation']['source_version_id'], $record->source_version_id);
        $this->assertSame($result['generation']['generated_version_id'], $record->generated_version_id);
        $this->assertSame(AiGenerationStatus::Completed, $record->status);
    }

    public function test_audit_record_never_contains_provider_secrets(): void
    {
        [$project] = $this->readyProjectWithScript();

        $this->generationService()->generate($project, ['topic' => 'Kenapa kucing suka kardus?']);

        $attributes = array_keys(AiGeneration::first()->getAttributes());
        $this->assertSame([
            'id',
            'script_id',
            'research_report_id',
            'provider',
            'model',
            'prompt_profile',
            'prompt_version',
            'source_version_id',
            'generated_version_id',
            'status',
            'created_at',
            'updated_at',
        ], $attributes);
    }

    public function test_generation_makes_no_external_http_requests(): void
    {
        Http::preventStrayRequests();
        [$project] = $this->readyProjectWithScript();

        $result = $this->generationService()->generate($project, ['topic' => 'Kenapa kucing suka kardus?']);

        $this->assertSame(2, $result['generated_version']->version);
    }

    public function test_generation_uses_bounded_query_count(): void
    {
        [$project] = $this->readyProjectWithScript();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->generationService()->generate($project, ['topic' => 'Kenapa kucing suka kardus?']);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(24, $queryCount);
    }

    private function makeReadyReport(ContentProject $project): void
    {
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claim = ResearchClaim::factory()->create([
            'research_report_id' => $report->id,
            'claim' => 'Cats purr to communicate with humans',
            'status' => ResearchClaimStatus::Supported,
            'importance' => ResearchClaimImportance::High,
        ]);
        $claim->sources()->attach($source->id);
    }
}
