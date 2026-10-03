<?php

namespace Tests\Feature;

use App\Enums\AssetRequirementAspectRatio;
use App\Enums\AssetRequirementStatus;
use App\Enums\AssetRequirementType;
use App\Enums\AssetType;
use App\Enums\ScriptStatus;
use App\Enums\VisualPlanItemType;
use App\Enums\VisualPlanStatus;
use App\Models\Asset;
use App\Models\AssetRequirement;
use App\Models\ContentProject;
use App\Models\Script;
use App\Models\User;
use App\Models\VisualPlan;
use App\Models\VisualPlanItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssetProductionManifestApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_endpoint_requires_authentication(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->planFor($project);

        $this->getJson($this->url($project))->assertUnauthorized();
    }

    #[Test]
    public function the_endpoint_returns_404_for_a_missing_project(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/projects/9999/script/versions/1/visual-plan/asset-manifest')
            ->assertNotFound();
    }

    #[Test]
    public function the_endpoint_returns_404_when_the_plan_belongs_to_another_project(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $otherProject = $this->projectFor($user);
        $this->planFor($otherProject);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertNotFound();
    }

    #[Test]
    public function it_returns_the_manifest_structure(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedVideo($project);
        $this->fulfilledRequirement($item, $asset);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.summary.total_requirements', 1)
            ->assertJsonPath('data.summary.valid_entries', 1)
            ->assertJsonPath('data.summary.invalid_requirements', 0)
            ->assertJsonPath('data.entries.0.asset_id', $asset->id);

        $response->assertJsonStructure([
            'data' => [
                'visual_plan_id',
                'script_version_id',
                'status',
                'ready',
                'summary' => ['total_requirements', 'valid_entries', 'invalid_requirements'],
                'entries' => [
                    [
                        'visual_plan_item_id',
                        'visual_plan_item_order',
                        'asset_requirement_id',
                        'requirement_type',
                        'asset_id',
                        'asset_type',
                        'file_name',
                        'mime_type',
                        'duration_seconds',
                        'width',
                        'height',
                        'aspect_ratio',
                        'target_duration_seconds',
                        'status',
                    ],
                ],
                'issues',
            ],
        ]);
    }

    #[Test]
    public function an_incomplete_manifest_reports_invalid_requirements(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);

        AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'status' => AssetRequirementStatus::Searching,
            'selected_asset_id' => null,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk()
            ->assertJsonPath('data.ready', false)
            ->assertJsonPath('data.summary.invalid_requirements', 1)
            ->assertJsonPath('data.entries', [])
            ->assertJsonPath('data.issues.0.code', 'REQUIREMENT_NOT_FULFILLED');
    }

    #[Test]
    public function it_never_exposes_a_file_path_private_path_or_public_url(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $asset = $this->verifiedVideo($project, [
            'file_path' => 'projects/secret-folder/asset.mp4',
        ]);
        $this->fulfilledRequirement($item, $asset);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk();

        $response->assertJsonMissingPath('data.entries.0.file_path');
        $response->assertJsonMissingPath('data.entries.0.preview_url');

        $content = $response->getContent();
        $this->assertStringNotContainsString('file_path', $content);
        $this->assertStringNotContainsString('preview_url', $content);
        $this->assertStringNotContainsString('projects/secret-folder', $content);
        $this->assertStringNotContainsString('storage/', $content);
    }

    #[Test]
    public function a_cross_project_selected_asset_is_invalid_and_its_metadata_is_not_leaked(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $otherProject = $this->projectFor($user);
        $plan = $this->planFor($project);
        $item = $this->itemFor($plan);
        $foreign = $this->verifiedVideo($otherProject, ['file_name' => 'secret.mp4']);
        $this->fulfilledRequirement($item, $foreign);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk()
            ->assertJsonPath('data.ready', false)
            ->assertJsonPath('data.entries', [])
            ->assertJsonPath('data.issues.0.code', 'SELECTED_ASSET_CROSS_PROJECT');

        $this->assertStringNotContainsString('secret.mp4', $response->getContent());
    }

    private function url(ContentProject $project, int $version = 1): string
    {
        return "/api/v1/projects/{$project->id}/script/versions/{$version}/visual-plan/asset-manifest";
    }

    private function projectFor(User $user): ContentProject
    {
        return ContentProject::factory()->for($user, 'creator')->create();
    }

    private function scriptFor(ContentProject $project, int $version = 1): Script
    {
        $script = $project->script()->first() ?? Script::factory()->create(['content_project_id' => $project->id]);

        $versionModel = $script->versions()->create([
            'version' => $version,
            'title' => 'How Cats Purr',
            'hook' => 'Why do cats purr?',
            'body' => "Cats purr to communicate with humans.\n",
            'closing' => 'And now you know.',
            'duration_seconds' => 45,
            'notes' => null,
        ]);

        $script->update(['current_version_id' => $versionModel->id, 'status' => ScriptStatus::Review]);
        $script->load('currentVersion');

        return $script;
    }

    private function planFor(ContentProject $project, int $version = 1): VisualPlan
    {
        $script = $this->scriptFor($project, $version);

        return VisualPlan::factory()->create([
            'content_project_id' => $project->id,
            'script_version_id' => $script->currentVersion->id,
            'status' => VisualPlanStatus::Draft,
        ]);
    }

    private function itemFor(VisualPlan $plan): VisualPlanItem
    {
        return $plan->items()->create([
            'order' => 1,
            'section' => 'body',
            'narration_text' => 'Cats purr to communicate with humans.',
            'visual_type' => VisualPlanItemType::AnimalClip,
            'visual_prompt' => 'Close up of a cat purring on a blanket.',
            'duration_seconds' => 5,
            'notes' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function verifiedVideo(ContentProject $project, array $overrides = []): Asset
    {
        return Asset::factory()->withVerifiedFile()->create(array_merge([
            'content_project_id' => $project->id,
            'type' => AssetType::Video,
            'file_name' => 'cat-walking.mp4',
            'duration_seconds' => 10,
            'width' => 1080,
            'height' => 1920,
            'aspect_ratio' => 0.5625,
        ], $overrides));
    }

    private function fulfilledRequirement(VisualPlanItem $item, Asset $asset): AssetRequirement
    {
        $requirement = AssetRequirement::factory()->create([
            'visual_plan_item_id' => $item->id,
            'requirement_type' => AssetRequirementType::Video,
            'aspect_ratio' => AssetRequirementAspectRatio::Portrait916,
            'target_duration_seconds' => 5,
            'status' => AssetRequirementStatus::Fulfilled,
            'selected_asset_id' => $asset->id,
        ]);

        $requirement->assets()->attach($asset->id);

        return $requirement;
    }
}
