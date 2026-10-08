<?php

namespace Tests\Feature;

use App\Enums\AssetMetadataStatus;
use App\Enums\AssetType;
use App\Enums\ScriptStatus;
use App\Enums\VisualPlanItemType;
use App\Enums\VisualPlanSection;
use App\Exceptions\AssetMetadataException;
use App\Exceptions\TtsProviderException;
use App\Models\Asset;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Script;
use App\Models\Source;
use App\Models\User;
use App\Models\VisualPlan;
use App\Services\AssetFileStorageService;
use App\Services\AssetMetadataService;
use App\Services\Metadata\AssetMetadataInspector;
use App\Services\TTS\Contracts\TtsProvider;
use App\Services\TTS\DTO\TtsRequest;
use App\Services\TTS\DTO\TtsResult;
use App\Services\TTS\TtsProviderRegistry;
use App\Services\VisualPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TtsGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake((string) config('assets.disk'));

        // Every generation test runs the real synthesis pipeline, so the
        // measurement step has to be predictable: a fake inspector answers
        // with a fixed duration instead of anything depending on a decoder.
        $this->withInspector($this->inspectorReporting(
            AssetType::Audio,
            ['width' => null, 'height' => null, 'duration_seconds' => 5]
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function readyProject(User $user): ContentProject
    {
        $project = ContentProject::factory()->for($user, 'creator')->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        $claims = [
            'Cats purr to communicate with humans',
            'Purring helps cats soothe themselves when stressed',
        ];
        foreach ($claims as $claimText) {
            $claim = ResearchClaim::factory()->create([
                'research_report_id' => $report->id,
                'claim' => $claimText,
                'status' => 'supported',
                'importance' => 'high',
            ]);
            $claim->sources()->attach($source->id);
        }

        return $project;
    }

    private function reviewScript(ContentProject $project, int $version = 1): Script
    {
        $script = $project->script()->first() ?? Script::factory()->create(['content_project_id' => $project->id]);

        $versionModel = $script->versions()->create([
            'version' => $version,
            'title' => 'How Cats Purr',
            'hook' => 'Why do cats purr?',
            'body' => "Cats purr to communicate with humans, and purring helps cats soothe themselves when stressed.\n",
            'closing' => 'And now you know.',
            'duration_seconds' => 45,
            'notes' => null,
        ]);

        $script->update(['current_version_id' => $versionModel->id, 'status' => ScriptStatus::Review]);
        $script->load('currentVersion');

        return $script;
    }

    private function service(): VisualPlanService
    {
        return app(VisualPlanService::class);
    }

    private function planWithItems(ContentProject $project, int $version = 1, int $itemCount = 1): VisualPlan
    {
        $this->reviewScript($project, $version);
        $plan = $this->service()->createVisualPlan($project, $version, []);

        foreach (range(1, $itemCount) as $index) {
            $plan->items()->create([
                'order' => $index,
                'section' => VisualPlanSection::Body->value,
                'narration_text' => "Narration line {$index}.",
                'visual_type' => VisualPlanItemType::StockVideo->value,
                'visual_prompt' => "Visual prompt {$index}.",
                'duration_seconds' => 5,
                'notes' => null,
            ]);
        }

        return $plan;
    }

    private function ttsUrl(ContentProject $project, int $version, int $item): string
    {
        return "/api/v1/projects/{$project->id}/script/versions/{$version}/visual-plan/items/{$item}/tts";
    }

    private function generatePayload(array $overrides = []): array
    {
        return array_merge([
            'voice' => 'neutral',
        ], $overrides);
    }

    private function withInspector(AssetMetadataInspector $inspector): void
    {
        $this->app->instance(
            AssetMetadataService::class,
            new AssetMetadataService(new AssetFileStorageService, [$inspector])
        );
    }

    private function inspectorReporting(AssetType $type, array $result, ?\Throwable $failure = null): AssetMetadataInspector
    {
        return new class($type, $result, $failure) implements AssetMetadataInspector
        {
            public function __construct(
                private AssetType $type,
                private array $result,
                private ?\Throwable $failure
            ) {}

            public function handles(): AssetType
            {
                return $this->type;
            }

            public function inspect(string $absolutePath, string $mimeType): array
            {
                if ($this->failure !== null) {
                    throw $this->failure;
                }

                return $this->result;
            }
        };
    }

    private function throwingProvider(): TtsProviderRegistry
    {
        return new TtsProviderRegistry([
            'fake' => new class implements TtsProvider
            {
                public function name(): string
                {
                    return 'fake';
                }

                public function synthesize(TtsRequest $request): TtsResult
                {
                    throw new TtsProviderException(
                        'provider-internal shaped noise',
                        TtsProviderException::UNAVAILABLE
                    );
                }
            },
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_tts_endpoints_require_authentication(): void
    {
        Http::preventStrayRequests();

        $base = '/api/v1/projects/1/script/versions/1/visual-plan/items/1/tts';

        $this->getJson($base)->assertUnauthorized();
        $this->postJson($base, $this->generatePayload())->assertUnauthorized();
        $this->postJson("{$base}/1/regenerate", $this->generatePayload())->assertUnauthorized();
        $this->deleteJson("{$base}/1")->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_index_returns_200_with_an_empty_list_and_the_current_hash(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project)->items()->first();

        $this->actingAs($user, 'sanctum')
            ->getJson($this->ttsUrl($project, 1, $item->id))
            ->assertOk()
            ->assertJsonPath('data.tracks', [])
            ->assertJsonPath('data.current_narration_hash', hash('sha256', (string) $item->narration_text));
    }

    #[Test]
    public function test_index_returns_404_when_the_item_does_not_exist(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->planWithItems($project);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->ttsUrl($project, 1, 9999))
            ->assertNotFound();
    }

    #[Test]
    public function test_index_returns_404_when_no_plan_exists(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->reviewScript($project);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->ttsUrl($project, 1, 1))
            ->assertNotFound();
    }

    #[Test]
    public function test_index_returns_404_for_an_item_of_another_project(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->planWithItems($project);
        $otherProject = $this->readyProject($user);
        $otherItem = $this->planWithItems($otherProject)->items()->first();

        $this->actingAs($user, 'sanctum')
            ->getJson($this->ttsUrl($project, 1, $otherItem->id))
            ->assertNotFound();
    }

    #[Test]
    public function test_index_reports_staleness_against_the_current_narration(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project)->items()->first();
        $current = (string) $item->narration_text;

        $stale = $project->ttsTracks()->create([
            'visual_plan_item_id' => $item->id,
            'provider' => 'fake',
            'model' => null,
            'voice' => 'neutral',
            'language' => null,
            'speed' => '1.000',
            'status' => 'completed',
            'duration_seconds' => 5,
            'narration_snapshot' => 'An earlier wording entirely.',
            'narration_hash' => hash('sha256', 'An earlier wording entirely.'),
            'error' => null,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->ttsUrl($project, 1, $item->id))
            ->assertOk()
            ->assertJsonPath('data.tracks.0.is_stale', true)
            ->assertJsonPath('data.current_narration_hash', hash('sha256', $current));

        $this->assertNotSame(hash('sha256', $current), $stale->fresh()->narration_hash);
    }

    /*
    |--------------------------------------------------------------------------
    | Store
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_store_requires_a_voice_and_validates_speed(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project)->items()->first();
        $url = $this->ttsUrl($project, 1, $item->id);

        $this->actingAs($user, 'sanctum')
            ->postJson($url, [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('voice');

        $this->actingAs($user, 'sanctum')
            ->postJson($url, $this->generatePayload(['speed' => 3]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('speed');
    }

    #[Test]
    public function test_store_refuses_an_empty_narration_without_leaving_a_track(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project);
        $item = $plan->items()->first();
        $item->update(['narration_text' => "  \t  "]);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->ttsUrl($project, 1, $item->id), $this->generatePayload())
            ->assertStatus(422)
            ->assertJsonPath('message', 'The narration for this visual plan item is empty.');

        $this->assertDatabaseCount('tts_tracks', 0);
    }

    #[Test]
    public function test_store_returns_201_with_a_completed_track_and_a_stored_asset(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project)->items()->first();
        $narration = (string) $item->narration_text;

        $response = $this->actingAs($user, 'sanctum')
            ->postJson($this->ttsUrl($project, 1, $item->id), $this->generatePayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.status_label', 'Completed')
            ->assertJsonPath('data.provider', 'fake')
            ->assertJsonPath('data.voice', 'neutral')
            ->assertJsonPath('data.speed', 1)
            ->assertJsonPath('data.narration_snapshot', $narration)
            ->assertJsonPath('data.narration_hash', hash('sha256', $narration))
            ->assertJsonPath('data.is_stale', false)
            ->assertJsonPath('data.error', null)
            ->assertJsonPath('data.duration_seconds', 5);

        $trackId = $response->json('data.id');
        $this->assertNotNull($trackId);

        $this->assertDatabaseHas('tts_tracks', [
            'id' => $trackId,
            'visual_plan_item_id' => $item->id,
            'content_project_id' => $project->id,
            'status' => 'completed',
            'duration_seconds' => 5,
        ]);

        $this->assertDatabaseHas('assets', [
            'content_project_id' => $project->id,
            'type' => AssetType::Audio->value,
        ]);

        $assetId = $response->json('data.asset_id');
        $this->assertIsInt($assetId);

        $asset = $project->assets()->whereKey($assetId)->first();
        $this->assertNotNull($asset);
        $this->assertSame(AssetMetadataStatus::Completed, $asset->metadata_status);
        $this->assertSame(5, $asset->duration_seconds);
        $this->assertTrue($asset->isTtsGenerated());
    }

    #[Test]
    public function test_store_preserves_the_exact_narration_bytes(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project);
        $item = $plan->items()->first();
        $item->update(['narration_text' => '  Narration with padding.  ']);
        $narration = (string) $item->fresh()->narration_text;

        $this->actingAs($user, 'sanctum')
            ->postJson($this->ttsUrl($project, 1, $item->id), $this->generatePayload())
            ->assertCreated()
            ->assertJsonPath('data.narration_snapshot', $narration)
            ->assertJsonPath('data.narration_hash', hash('sha256', $narration));
    }

    #[Test]
    public function test_store_takes_the_message_from_the_measurement_not_the_provider(): void
    {
        Http::preventStrayRequests();

        // The fake provider would claim ceil(3 words / 3) = 1 second for this
        // narration; the inspector reporting a different value is the decider.
        $this->withInspector($this->inspectorReporting(
            AssetType::Audio,
            ['width' => null, 'height' => null, 'duration_seconds' => 7]
        ));

        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project)->items()->first();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson($this->ttsUrl($project, 1, $item->id), $this->generatePayload())
            ->assertCreated();

        $this->assertSame(7, $response->json('data.duration_seconds'));
    }

    #[Test]
    public function test_store_leaves_duration_null_when_the_file_cannot_be_measured(): void
    {
        Http::preventStrayRequests();
        $this->withInspector($this->inspectorReporting(
            AssetType::Audio,
            [],
            new AssetMetadataException('Corrupt audio header.')
        ));

        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project)->items()->first();

        $this->actingAs($user, 'sanctum')
            ->postJson($this->ttsUrl($project, 1, $item->id), $this->generatePayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.duration_seconds', null);
    }

    #[Test]
    public function test_store_returns_502_and_a_failed_track_when_the_provider_throws(): void
    {
        Http::preventStrayRequests();
        $this->app->instance(TtsProviderRegistry::class, $this->throwingProvider());

        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project)->items()->first();

        $this->actingAs($user, 'sanctum')
            ->postJson($this->ttsUrl($project, 1, $item->id), $this->generatePayload())
            ->assertStatus(502)
            ->assertJsonPath('message', 'The TTS provider could not complete the request.');

        $track = $item->ttsTracks()->first();
        $this->assertNotNull($track);
        $this->assertSame('failed', $track->status->value);
        $this->assertSame('TTS synthesis failed.', $track->error);
        $this->assertStringNotContainsString('provider-internal', $track->error);

        $this->assertDatabaseCount('assets', 0);
    }

    #[Test]
    public function test_store_returns_502_for_an_unknown_provider(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project)->items()->first();

        $this->actingAs($user, 'sanctum')
            ->postJson($this->ttsUrl($project, 1, $item->id), $this->generatePayload(['provider' => 'ghost']))
            ->assertStatus(502)
            ->assertJsonPath('message', 'The TTS provider could not complete the request.');

        $this->assertDatabaseHas('tts_tracks', [
            'visual_plan_item_id' => $item->id,
            'status' => 'failed',
            'provider' => 'ghost',
        ]);
    }

    #[Test]
    public function test_store_returns_404_for_an_item_of_another_project(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $this->planWithItems($project);
        $otherProject = $this->readyProject($user);
        $otherItem = $this->planWithItems($otherProject)->items()->first();

        $this->actingAs($user, 'sanctum')
            ->postJson($this->ttsUrl($project, 1, $otherItem->id), $this->generatePayload())
            ->assertNotFound();
    }

    #[Test]
    public function test_the_resource_never_publishes_a_file_path(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project)->items()->first();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson($this->ttsUrl($project, 1, $item->id), $this->generatePayload())
            ->assertCreated();

        $this->assertArrayNotHasKey('file_path', $response->json('data'));
        $this->assertStringContainsString('/preview', (string) $response->json('data.preview_url'));
    }

    /*
    |--------------------------------------------------------------------------
    | Regenerate
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_regenerate_creates_a_new_track_and_leaves_the_old_one_untouched(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project)->items()->first();

        $first = $this->actingAs($user, 'sanctum')
            ->postJson($this->ttsUrl($project, 1, $item->id), $this->generatePayload())
            ->assertCreated()
            ->json('data');

        $item->update(['narration_text' => 'Narration line 1, updated.']);
        $updatedNarration = (string) $item->fresh()->narration_text;

        $second = $this->actingAs($user, 'sanctum')
            ->postJson($this->ttsUrl($project, 1, $item->id)."/{$first['id']}/regenerate", $this->generatePayload())
            ->assertCreated()
            ->json('data');

        $this->assertNotSame($first['id'], $second['id']);
        $this->assertSame('completed', $second['status']);
        $this->assertSame($updatedNarration, $second['narration_snapshot']);
        $this->assertSame(hash('sha256', $updatedNarration), $second['narration_hash']);

        $old = $item->ttsTracks()->whereKey($first['id'])->first();
        $this->assertSame('completed', $old->status->value);
        $this->assertSame($first['asset_id'], $old->asset_id);

        $this->assertCount(2, $item->ttsTracks()->get());
    }

    #[Test]
    public function test_regenerate_returns_404_when_the_track_belongs_to_another_item(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $plan = $this->planWithItems($project, 1, 2);
        $items = $plan->items()->orderBy('order')->get();
        $first = $items[0];
        $second = $items[1];

        $foreign = $project->ttsTracks()->create([
            'visual_plan_item_id' => $second->id,
            'provider' => 'fake',
            'model' => null,
            'voice' => 'neutral',
            'language' => null,
            'speed' => '1.000',
            'status' => 'completed',
            'duration_seconds' => 5,
            'narration_snapshot' => 'Narration line 2.',
            'narration_hash' => hash('sha256', 'Narration line 2.'),
            'error' => null,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->ttsUrl($project, 1, $first->id)."/{$foreign->id}/regenerate", $this->generatePayload())
            ->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Archive (delete = archive)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_archive_keeps_the_asset_and_its_file(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project)->items()->first();

        $track = $project->ttsTracks()->create([
            'visual_plan_item_id' => $item->id,
            'provider' => 'fake',
            'model' => null,
            'voice' => 'neutral',
            'language' => null,
            'speed' => '1.000',
            'status' => 'completed',
            'duration_seconds' => 5,
            'narration_snapshot' => 'Narration line 1.',
            'narration_hash' => hash('sha256', 'Narration line 1.'),
            'error' => null,
        ]);

        $asset = Asset::factory()->create([
            'content_project_id' => $project->id,
            'type' => AssetType::Audio->value,
            'file_path' => 'assets/'.$project->id.'/tts.wav',
            'file_name' => 'tts.wav',
            'mime_type' => 'audio/wav',
            'file_size' => 100,
        ]);
        Storage::disk((string) config('assets.disk'))->put('assets/'.$project->id.'/tts.wav', 'RIFFbytes');
        $track->forceFill(['asset_id' => $asset->id])->save();

        $this->actingAs($user, 'sanctum')
            ->deleteJson($this->ttsUrl($project, 1, $item->id)."/{$track->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'archived')
            ->assertJsonPath('data.status_label', 'Archived')
            ->assertJsonPath('data.asset_id', $asset->id);

        $this->assertSame('archived', $track->fresh()->status->value);
        $this->assertModelExists($asset->fresh());
        $this->assertSame($asset->id, $track->fresh()->asset_id);
        Storage::disk((string) config('assets.disk'))->assertExists('assets/'.$project->id.'/tts.wav');
    }

    #[Test]
    public function test_archiving_an_already_archived_track_returns_422(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project)->items()->first();

        $track = $project->ttsTracks()->create([
            'visual_plan_item_id' => $item->id,
            'provider' => 'fake',
            'model' => null,
            'voice' => 'neutral',
            'language' => null,
            'speed' => '1.000',
            'status' => 'archived',
            'duration_seconds' => null,
            'narration_snapshot' => 'Narration line 1.',
            'narration_hash' => hash('sha256', 'Narration line 1.'),
            'error' => null,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson($this->ttsUrl($project, 1, $item->id)."/{$track->id}")
            ->assertStatus(422);
    }

    #[Test]
    public function test_archive_returns_404_for_a_track_that_does_not_exist(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->planWithItems($project)->items()->first();

        $this->actingAs($user, 'sanctum')
            ->deleteJson($this->ttsUrl($project, 1, $item->id).'/9999')
            ->assertNotFound();
    }
}
