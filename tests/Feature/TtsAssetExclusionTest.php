<?php

namespace Tests\Feature;

use App\Enums\AssetType;
use App\Enums\ScriptStatus;
use App\Enums\VisualPlanItemType;
use App\Enums\VisualPlanSection;
use App\Models\Asset;
use App\Models\AssetRequirement;
use App\Models\ContentProject;
use App\Models\ResearchClaim;
use App\Models\ResearchReport;
use App\Models\Script;
use App\Models\Source;
use App\Models\TtsTrack;
use App\Models\User;
use App\Models\VisualPlan;
use App\Models\VisualPlanItem;
use App\Services\AssetFileStorageService;
use App\Services\AssetMetadataService;
use App\Services\Metadata\AssetMetadataInspector;
use App\Services\VisualPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The requirement guards: an asset that is the audio of a TTS track must never
 * become a requirement candidate or selection, while an ordinary audio upload
 * stays fully usable.
 */
class TtsAssetExclusionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake((string) config('assets.disk'));

        $this->app->instance(
            AssetMetadataService::class,
            new AssetMetadataService(new AssetFileStorageService, [
                $this->inspectorReporting(
                    AssetType::Audio,
                    ['width' => null, 'height' => null, 'duration_seconds' => 5]
                ),
            ])
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function inspectorReporting(AssetType $type, array $result): AssetMetadataInspector
    {
        return new class($type, $result) implements AssetMetadataInspector
        {
            public function __construct(
                private AssetType $type,
                private array $result
            ) {}

            public function handles(): AssetType
            {
                return $this->type;
            }

            public function inspect(string $absolutePath, string $mimeType): array
            {
                return $this->result;
            }
        };
    }

    private function readyProject(User $user): ContentProject
    {
        $project = ContentProject::factory()->for($user, 'creator')->create();
        $report = ResearchReport::factory()->create(['content_project_id' => $project->id]);
        $source = Source::factory()->create(['research_report_id' => $report->id]);
        foreach (['Cats purr to communicate with humans'] as $claimText) {
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

    private function planWithItem(ContentProject $project): VisualPlan
    {
        $script = $project->script()->first() ?? Script::factory()->create(['content_project_id' => $project->id]);

        $versionModel = $script->versions()->create([
            'version' => 1,
            'title' => 'How Cats Purr',
            'hook' => 'Why do cats purr?',
            'body' => "Cats purr to communicate with humans.\n",
            'closing' => 'And now you know.',
            'duration_seconds' => 45,
            'notes' => null,
        ]);

        $script->update(['current_version_id' => $versionModel->id, 'status' => ScriptStatus::Review]);
        $script->load('currentVersion');

        $plan = app(VisualPlanService::class)->createVisualPlan($project, 1, []);

        $plan->items()->create([
            'order' => 1,
            'section' => VisualPlanSection::Body->value,
            'narration_text' => 'Narration line 1.',
            'visual_type' => VisualPlanItemType::StockVideo->value,
            'visual_prompt' => 'Visual prompt 1.',
            'duration_seconds' => 5,
            'notes' => null,
        ]);

        return $plan;
    }

    private function itemOf(VisualPlan $plan): VisualPlanItem
    {
        return $plan->items()->first();
    }

    private function requirementFor(VisualPlanItem $item): AssetRequirement
    {
        return AssetRequirement::factory()->create(['visual_plan_item_id' => $item->id]);
    }

    private function audioOf(ContentProject $project): Asset
    {
        return Asset::factory()->create([
            'content_project_id' => $project->id,
            'type' => AssetType::Audio,
        ]);
    }

    private function ttsAsset(ContentProject $project, VisualPlanItem $item, Asset $asset): TtsTrack
    {
        return $project->ttsTracks()->create([
            'visual_plan_item_id' => $item->id,
            'asset_id' => $asset->id,
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
    }

    private function attachUrl(ContentProject $project, AssetRequirement $requirement): string
    {
        return "/api/v1/projects/{$project->id}/script/versions/1/visual-plan"
            ."/items/{$requirement->visual_plan_item_id}/asset-requirements/{$requirement->id}/assets";
    }

    private function selectUrl(ContentProject $project, AssetRequirement $requirement): string
    {
        return "/api/v1/projects/{$project->id}/script/versions/1/visual-plan"
            ."/items/{$requirement->visual_plan_item_id}/asset-requirements/{$requirement->id}/select";
    }

    /*
    |--------------------------------------------------------------------------
    | The guards
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_attach_rejects_a_tts_generated_asset_with_422(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->itemOf($this->planWithItem($project));
        $requirement = $this->requirementFor($item);
        $asset = $this->audioOf($project);
        $this->ttsAsset($project, $item, $asset);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->attachUrl($project, $requirement), ['asset_id' => $asset->id])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'A TTS-generated asset cannot be attached to an asset requirement.');

        $this->assertDatabaseCount('asset_requirement_asset', 0);
    }

    #[Test]
    public function test_select_rejects_a_tts_generated_asset_with_422(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->itemOf($this->planWithItem($project));
        $requirement = $this->requirementFor($item);
        $asset = $this->audioOf($project);
        $this->ttsAsset($project, $item, $asset);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->selectUrl($project, $requirement), ['asset_id' => $asset->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A TTS-generated asset cannot be selected for an asset requirement.');

        $this->assertNull($requirement->fresh()->selected_asset_id);
    }

    #[Test]
    public function test_an_ordinary_audio_asset_can_still_be_attached(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->itemOf($this->planWithItem($project));
        $requirement = $this->requirementFor($item);
        $audio = $this->audioOf($project);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->attachUrl($project, $requirement), ['asset_id' => $audio->id])
            ->assertCreated()
            ->assertJsonPath('data.id', $audio->id);

        $this->assertDatabaseHas('asset_requirement_asset', [
            'asset_requirement_id' => $requirement->id,
            'asset_id' => $audio->id,
        ]);
    }

    #[Test]
    public function test_a_generated_tts_asset_cannot_be_attached(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->readyProject($user);
        $item = $this->itemOf($this->planWithItem($project));
        $requirement = $this->requirementFor($item);

        $generated = $this->actingAs($user, 'sanctum')
            ->postJson(
                "/api/v1/projects/{$project->id}/script/versions/1/visual-plan/items/{$item->id}/tts",
                ['voice' => 'neutral']
            )
            ->assertCreated()
            ->json('data');

        $track = $item->fresh()->ttsTracks()->whereKey($generated['id'])->first();
        $this->assertTrue($track->fresh()->asset->isTtsGenerated());

        $this->actingAs($user, 'sanctum')
            ->postJson($this->attachUrl($project, $requirement), ['asset_id' => $generated['asset_id']])
            ->assertStatus(422)
            ->assertJsonPath('message', 'A TTS-generated asset cannot be attached to an asset requirement.');

        $this->assertSame(0, DB::table('asset_requirement_asset')->where('asset_requirement_id', $requirement->id)->count());
    }
}
