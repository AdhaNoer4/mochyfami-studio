<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\ContentProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The asset lifecycle endpoint.
 *
 * AssetStatusTest already owns the complete matrix of which pairs the state
 * machine accepts and which it refuses, so nothing here re-tests that graph in
 * isolation. What these tests prove is that this endpoint applies it: that the
 * request is scoped to the project, that the policy is consulted, that the user
 * is told why a transition was refused, and that a refused transition writes
 * nothing.
 */
class AssetStatusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function makeAsset(AssetStatus $status, ?ContentProject $project = null): Asset
    {
        return Asset::factory()->create([
            'content_project_id' => ($project ?? $this->makeProject())->id,
            'status' => $status,
        ]);
    }

    private function makeProject(?User $owner = null): ContentProject
    {
        return ContentProject::factory()->for($owner ?? $this->user, 'creator')->create();
    }

    private function statusUrl(ContentProject $project, int $assetId): string
    {
        return "/api/v1/projects/{$project->id}/assets/{$assetId}/status";
    }

    private function transition(ContentProject $project, Asset $asset, AssetStatus $target): TestResponse
    {
        return $this->actingAs($this->user, 'sanctum')
            ->patchJson($this->statusUrl($project, $asset->id), ['status' => $target->value]);
    }

    #[Test]
    public function test_allows_a_transition_the_state_machine_permits_and_persists_it(): void
    {
        $project = $this->makeProject();
        $asset = $this->makeAsset(AssetStatus::Pending, $project);

        $this->transition($project, $asset, AssetStatus::Available)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Asset status updated successfully.')
            ->assertJsonPath('data.id', $asset->id)
            ->assertJsonPath('data.status', 'available')
            ->assertJsonPath('data.status_label', 'Available');

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'status' => AssetStatus::Available->value,
        ]);
    }

    #[Test]
    public function test_rejects_a_transition_the_state_machine_forbids_with_422(): void
    {
        $project = $this->makeProject();
        $asset = $this->makeAsset(AssetStatus::Pending, $project);

        $this->transition($project, $asset, AssetStatus::Approved)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Asset cannot transition from pending to approved.');

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'status' => AssetStatus::Pending->value,
        ]);
    }

    #[Test]
    public function test_rejects_the_status_the_asset_is_already_in_with_422(): void
    {
        $project = $this->makeProject();
        $asset = $this->makeAsset(AssetStatus::Available, $project);

        $this->transition($project, $asset, AssetStatus::Available)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Asset is already in available status.');

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'status' => AssetStatus::Available->value,
        ]);
    }

    public static function invalidStatusProvider(): array
    {
        return [
            'unknown status' => ['not_a_status'],
            'status of another domain' => ['approved_script'],
            'numeric value' => [1],
            'empty string' => [''],
        ];
    }

    #[DataProvider('invalidStatusProvider')]
    public function test_rejects_a_value_that_is_not_an_asset_status_with_422(mixed $status): void
    {
        $project = $this->makeProject();
        $asset = $this->makeAsset(AssetStatus::Pending, $project);

        $this->actingAs($this->user, 'sanctum')
            ->patchJson($this->statusUrl($project, $asset->id), ['status' => $status])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'status' => AssetStatus::Pending->value,
        ]);
    }

    #[Test]
    public function test_rejects_a_request_without_a_status_with_422(): void
    {
        $project = $this->makeProject();
        $asset = $this->makeAsset(AssetStatus::Pending, $project);

        $this->actingAs($this->user, 'sanctum')
            ->patchJson($this->statusUrl($project, $asset->id), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'status' => AssetStatus::Pending->value,
        ]);
    }

    #[Test]
    public function test_rejects_an_unauthenticated_request_with_401(): void
    {
        $project = $this->makeProject();
        $asset = $this->makeAsset(AssetStatus::Pending, $project);

        $this->patchJson($this->statusUrl($project, $asset->id), ['status' => 'available'])
            ->assertUnauthorized();

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'status' => AssetStatus::Pending->value,
        ]);
    }

    #[Test]
    public function test_denies_a_user_who_does_not_own_the_project_with_403(): void
    {
        $owner = User::factory()->create();
        $project = $this->makeProject($owner);
        $asset = $this->makeAsset(AssetStatus::Pending, $project);

        $this->transition($project, $asset, AssetStatus::Available)
            ->assertForbidden();

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'status' => AssetStatus::Pending->value,
        ]);
    }

    #[Test]
    public function test_denies_a_user_when_the_project_has_no_creator_with_403(): void
    {
        $project = $this->makeProject();
        $asset = $this->makeAsset(AssetStatus::Pending, $project);
        $project->update(['created_by' => null]);

        $this->transition($project, $asset, AssetStatus::Available)
            ->assertForbidden();

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'status' => AssetStatus::Pending->value,
        ]);
    }

    #[Test]
    public function test_answers_404_for_an_asset_that_belongs_to_another_project(): void
    {
        $project = $this->makeProject();
        $otherProject = $this->makeProject();
        $asset = $this->makeAsset(AssetStatus::Pending, $otherProject);

        $this->transition($project, $asset, AssetStatus::Available)
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Asset not found for this project.');

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'status' => AssetStatus::Pending->value,
        ]);
    }

    #[Test]
    public function test_answers_404_for_an_asset_that_does_not_exist(): void
    {
        $project = $this->makeProject();

        $this->actingAs($this->user, 'sanctum')
            ->patchJson($this->statusUrl($project, 9999), ['status' => 'available'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Asset not found for this project.');
    }

    #[Test]
    public function test_answers_404_for_a_project_that_does_not_exist(): void
    {
        $asset = $this->makeAsset(AssetStatus::Pending);

        $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/projects/9999/assets/{$asset->id}/status", ['status' => 'available'])
            ->assertNotFound();
    }

    #[DataProvider('allowedTransitionsProvider')]
    public function test_the_resource_publishes_the_transitions_allowed_from_the_current_status(AssetStatus $status): void
    {
        $project = $this->makeProject();
        $asset = $this->makeAsset($status, $project);

        $expected = array_map(
            fn (AssetStatus $target) => ['status' => $target->value, 'label' => $target->label()],
            $status->allowedTransitions()
        );

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/assets/{$asset->id}")
            ->assertOk()
            ->assertJsonPath('data.status', $status->value)
            ->assertJsonCount(count($expected), 'data.allowed_transitions')
            ->assertJsonPath('data.allowed_transitions', $expected);
    }

    public static function allowedTransitionsProvider(): array
    {
        return array_map(
            fn (AssetStatus $status) => [$status],
            AssetStatus::cases()
        );
    }

    #[Test]
    public function test_the_published_transitions_narrow_after_a_transition(): void
    {
        $project = $this->makeProject();
        $asset = $this->makeAsset(AssetStatus::Pending, $project);

        $response = $this->transition($project, $asset, AssetStatus::Available)->assertOk();

        $this->assertSame(
            [
                ['status' => 'processing', 'label' => 'Processing'],
                ['status' => 'approved', 'label' => 'Approved'],
                ['status' => 'rejected', 'label' => 'Rejected'],
                ['status' => 'archived', 'label' => 'Archived'],
            ],
            $response->json('data.allowed_transitions')
        );
    }

    #[Test]
    public function test_a_transition_does_not_change_the_asset_metadata(): void
    {
        $project = $this->makeProject();
        $asset = Asset::factory()->create([
            'content_project_id' => $project->id,
            'status' => AssetStatus::Pending,
            'type' => AssetType::Audio,
            'title' => 'Cat purring',
            'notes' => 'Ambient loop.',
            'file_path' => null,
        ]);

        $this->transition($project, $asset, AssetStatus::Available)
            ->assertOk()
            ->assertJsonPath('data.type', 'audio')
            ->assertJsonPath('data.title', 'Cat purring')
            ->assertJsonPath('data.notes', 'Ambient loop.')
            ->assertJsonPath('data.has_file', false)
            ->assertJsonPath('data.preview_url', null);

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'type' => 'audio',
            'title' => 'Cat purring',
            'notes' => 'Ambient loop.',
            'file_path' => null,
        ]);
    }

    #[Test]
    public function test_the_response_of_a_transition_never_exposes_the_file_path(): void
    {
        $project = $this->makeProject();
        $asset = $this->makeAsset(AssetStatus::Available, $project);
        $asset->update(['file_path' => 'app/private/video/secret.mp4']);

        $response = $this->transition($project, $asset, AssetStatus::Approved)->assertOk();

        $this->assertArrayNotHasKey('file_path', $response->json('data'));
        $this->assertStringNotContainsString('file_path', $response->getContent());
        $this->assertStringNotContainsString('app/private/video', $response->getContent());
    }
}
