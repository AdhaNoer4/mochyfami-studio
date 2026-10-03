<?php

namespace Tests\Feature;

use App\Enums\ScriptStatus;
use App\Enums\VisualPlanItemType;
use App\Enums\VisualPlanStatus;
use App\Models\ContentProject;
use App\Models\Script;
use App\Models\User;
use App\Models\VisualPlan;
use App\Models\VisualPlanItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssetFulfillmentReadinessApiTest extends TestCase
{
    use RefreshDatabase;

    private function projectFor(User $user): ContentProject
    {
        return ContentProject::factory()->for($user, 'creator')->create();
    }

    private function scriptFor(ContentProject $project, int $version = 1): Script
    {
        $script = $project->script()->first() ?? Script::factory()->create([
            'content_project_id' => $project->id,
        ]);

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

    private function planFor(ContentProject $project, int $version = 1, array $state = []): VisualPlan
    {
        $script = $this->scriptFor($project, $version);

        return VisualPlan::factory()->create(array_merge([
            'content_project_id' => $project->id,
            'script_version_id' => $script->currentVersion->id,
            'status' => VisualPlanStatus::Draft,
        ], $state));
    }

    private function itemFor(VisualPlan $plan, array $state = []): VisualPlanItem
    {
        static $order = 0;
        $order++;

        return $plan->items()->create(array_merge([
            'order' => $order,
            'section' => 'body',
            'narration_text' => 'Cats purr to communicate with humans.',
            'visual_type' => VisualPlanItemType::AnimalClip,
            'visual_prompt' => 'Close up of a cat purring on a blanket.',
            'duration_seconds' => 5,
            'notes' => null,
        ], $state));
    }

    private function url(ContentProject $project, int $version = 1): string
    {
        return "/api/v1/projects/{$project->id}/script/versions/{$version}/visual-plan/asset-readiness";
    }

    #[Test]
    public function test_show_requires_authentication(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->planFor($project);

        $this->getJson($this->url($project))->assertUnauthorized();
    }

    #[Test]
    public function test_show_returns_404_when_the_project_does_not_exist(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/projects/9999/script/versions/1/visual-plan/asset-readiness')
            ->assertNotFound();
    }

    #[Test]
    public function test_show_returns_404_when_plan_does_not_exist_for_script_version(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $otherProject = $this->projectFor($user);
        $this->planFor($otherProject);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertNotFound();
    }

    #[Test]
    public function test_show_returns_correct_resource_structure_for_empty_plan(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $this->planFor($project);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->url($project))
            ->assertOk();

        $response->assertJsonStructure([
            'data' => [
                'visual_plan_id',
                'script_version_id',
                'status',
                'ready',
                'score',
                'summary',
                'items',
                'blockers',
                'warnings',
                'issues',
            ],
        ]);

        $response->assertJsonPath('data.ready', false);
    }
}
