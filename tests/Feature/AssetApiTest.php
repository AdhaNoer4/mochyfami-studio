<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\ContentProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssetApiTest extends TestCase
{
    use RefreshDatabase;

    private function projectFor(User $user): ContentProject
    {
        return ContentProject::factory()->for($user, 'creator')->create();
    }

    private function listUrl(ContentProject $project): string
    {
        return "/api/v1/projects/{$project->id}/assets";
    }

    private function showUrl(ContentProject $project, int $assetId): string
    {
        return "/api/v1/projects/{$project->id}/assets/{$assetId}";
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => AssetType::Video->value,
            'title' => 'Cat walking on the floor',
            'description' => 'A calm cat walking across a wooden floor.',
        ], $overrides);
    }

    #[Test]
    public function test_asset_endpoints_require_authentication(): void
    {
        Http::preventStrayRequests();

        $base = '/api/v1/projects/1/assets';

        $this->getJson($base)->assertUnauthorized();
        $this->postJson($base, $this->payload())->assertUnauthorized();
        $this->getJson("{$base}/1")->assertUnauthorized();
        $this->patchJson("{$base}/1", ['title' => 'x'])->assertUnauthorized();
        $this->post("{$base}/1/file", ['file' => UploadedFile::fake()->create('clip.mp4', 4, 'video/mp4')], [
            'Accept' => 'application/json',
        ])->assertUnauthorized();
        $this->deleteJson("{$base}/1")->assertUnauthorized();
    }

    #[Test]
    public function test_index_returns_200_with_an_empty_list_for_a_fresh_project(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items', []);
    }

    #[Test]
    public function test_index_lists_only_the_assets_of_that_project(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $other = ContentProject::factory()->for($user, 'creator')->create();

        Asset::factory()->create(['content_project_id' => $project->id, 'title' => 'Mine']);
        Asset::factory()->create(['content_project_id' => $other->id, 'title' => 'Theirs']);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Mine');
    }

    #[Test]
    public function test_store_creates_a_pending_asset_with_metadata(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->listUrl($project), $this->payload([
                'file_name' => 'cat-walking-01.mp4',
                'mime_type' => 'video/mp4',
                'file_size' => 4_194_304,
                'duration_seconds' => 6,
                'width' => 1080,
                'height' => 1920,
                'source_url' => 'https://example.com/cat-walking',
                'source_name' => 'Example Library',
                'license_type' => 'cc-by-4.0',
                'attribution' => 'Example Library',
                'notes' => 'Short loop.',
            ]))
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'video')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.content_project_id', $project->id)
            ->assertJsonPath('data.file_name', 'cat-walking-01.mp4')
            ->assertJsonPath('data.license_type', 'cc-by-4.0');

        $this->assertDatabaseHas('assets', [
            'content_project_id' => $project->id,
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function test_store_accepts_an_asset_with_only_a_type(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->listUrl($project), ['type' => AssetType::Other->value])
            ->assertCreated()
            ->assertJsonPath('data.title', null)
            ->assertJsonPath('data.source_url', null)
            ->assertJsonPath('data.file_name', null);
    }

    #[Test]
    public function test_store_ignores_a_client_supplied_status(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->listUrl($project), $this->payload(['status' => AssetStatus::Approved->value]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseMissing('assets', ['status' => AssetStatus::Approved->value]);
    }

    #[Test]
    public function test_store_never_persists_a_client_supplied_file_path(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->listUrl($project), $this->payload([
                'file_path' => '../../storage/app/private/secret.mp4',
            ]))
            ->assertCreated();

        $this->assertNull(Asset::first()->file_path);
    }

    #[Test]
    public function test_store_never_writes_to_disk_or_calls_the_network(): void
    {
        Http::preventStrayRequests();
        Storage::shouldReceive('disk')->never();

        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->listUrl($project), $this->payload([
                'source_url' => 'https://example.com/never-requested.mp4',
            ]))
            ->assertCreated();
    }

    #[Test]
    public function test_store_validates_the_type(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->listUrl($project), $this->payload(['type' => 'hologram']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    #[Test]
    public function test_store_requires_a_type(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->listUrl($project), ['title' => 'No type given'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    #[DataProvider('validAssetTypeProvider')]
    public function test_store_accepts_every_asset_type(string $type): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->listUrl($project), $this->payload(['type' => $type]))
            ->assertCreated()
            ->assertJsonPath('data.type', $type);
    }

    public static function validAssetTypeProvider(): array
    {
        return [
            'video' => ['video'],
            'image' => ['image'],
            'audio' => ['audio'],
            'other' => ['other'],
        ];
    }

    #[Test]
    public function test_store_rejects_a_negative_file_size(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->listUrl($project), $this->payload(['file_size' => -1]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('file_size');
    }

    #[Test]
    public function test_store_rejects_a_non_integer_file_size(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->listUrl($project), $this->payload(['file_size' => '4 MB']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('file_size');
    }

    #[Test]
    public function test_store_accepts_a_zero_file_size(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->listUrl($project), $this->payload(['file_size' => 0]))
            ->assertCreated()
            ->assertJsonPath('data.file_size', 0);
    }

    #[Test]
    public function test_store_rejects_a_negative_duration(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->listUrl($project), $this->payload(['duration_seconds' => -5]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('duration_seconds');
    }

    #[DataProvider('invalidDimensionProvider')]
    public function test_store_rejects_invalid_dimensions(string $field): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->listUrl($project), $this->payload([$field => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrors($field);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->listUrl($project), $this->payload([$field => -1080]))
            ->assertStatus(422)
            ->assertJsonValidationErrors($field);
    }

    public static function invalidDimensionProvider(): array
    {
        return [
            'width' => ['width'],
            'height' => ['height'],
        ];
    }

    #[Test]
    public function test_store_rejects_an_invalid_source_url(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->listUrl($project), $this->payload(['source_url' => 'not a url']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('source_url');
    }

    #[Test]
    public function test_store_rejects_an_over_long_title(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->listUrl($project), $this->payload(['title' => str_repeat('a', 256)]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('title');
    }

    #[Test]
    public function test_show_returns_the_asset_metadata(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = Asset::factory()->create([
            'content_project_id' => $project->id,
            'title' => 'Cat walking',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->showUrl($project, $asset->id))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $asset->id)
            ->assertJsonPath('data.title', 'Cat walking');
    }

    #[Test]
    public function test_show_returns_404_for_an_unknown_asset(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->showUrl($project, 999999))
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    #[Test]
    public function test_update_changes_only_the_sent_fields(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = Asset::factory()->create([
            'content_project_id' => $project->id,
            'title' => 'Original title',
            'description' => 'Original description',
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson($this->showUrl($project, $asset->id), ['title' => 'New title'])
            ->assertOk()
            ->assertJsonPath('data.title', 'New title')
            ->assertJsonPath('data.description', 'Original description');
    }

    #[Test]
    public function test_update_cannot_change_the_status(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = Asset::factory()->create(['content_project_id' => $project->id]);

        $this->actingAs($user, 'sanctum')
            ->patchJson($this->showUrl($project, $asset->id), [
                'title' => 'Renamed',
                'status' => AssetStatus::Archived->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseMissing('assets', ['status' => AssetStatus::Archived->value]);
    }

    #[Test]
    public function test_update_never_changes_the_file_path(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = Asset::factory()->create([
            'content_project_id' => $project->id,
            'file_path' => 'assets/original.mp4',
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson($this->showUrl($project, $asset->id), ['file_path' => '/etc/passwd'])
            ->assertOk();

        $this->assertSame('assets/original.mp4', $asset->fresh()->file_path);
    }

    #[Test]
    public function test_update_validates_its_fields(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = Asset::factory()->create(['content_project_id' => $project->id]);

        $this->actingAs($user, 'sanctum')
            ->patchJson($this->showUrl($project, $asset->id), ['type' => 'hologram'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    #[Test]
    public function test_update_returns_404_for_an_asset_of_another_project(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $foreign = Asset::factory()->create([
            'content_project_id' => $this->projectFor($user)->id,
            'title' => 'Untouched',
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson($this->showUrl($project, $foreign->id), ['title' => 'Hijacked'])
            ->assertNotFound();

        $this->assertSame('Untouched', $foreign->fresh()->title);
    }

    #[Test]
    public function test_destroy_removes_the_asset(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = Asset::factory()->create(['content_project_id' => $project->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson($this->showUrl($project, $asset->id))
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
    }

    #[Test]
    public function test_destroy_returns_404_for_an_asset_of_another_project(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $foreign = Asset::factory()->create([
            'content_project_id' => $this->projectFor($user)->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson($this->showUrl($project, $foreign->id))
            ->assertNotFound();

        $this->assertDatabaseHas('assets', ['id' => $foreign->id]);
    }

    #[Test]
    public function test_a_user_can_reach_the_assets_of_their_own_project(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = Asset::factory()->create(['content_project_id' => $project->id]);

        $this->actingAs($user, 'sanctum')->getJson($this->listUrl($project))->assertOk();
        $this->actingAs($user, 'sanctum')->getJson($this->showUrl($project, $asset->id))->assertOk();
        $this->actingAs($user, 'sanctum')
            ->patchJson($this->showUrl($project, $asset->id), ['title' => 'Mine'])
            ->assertOk();
        $this->actingAs($user, 'sanctum')->deleteJson($this->showUrl($project, $asset->id))->assertOk();
    }

    #[Test]
    public function test_a_user_cannot_read_the_assets_of_another_users_project(): void
    {
        Http::preventStrayRequests();
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = $this->projectFor($owner);
        Asset::factory()->count(2)->create(['content_project_id' => $project->id]);

        $this->actingAs($intruder, 'sanctum')->getJson($this->listUrl($project))->assertForbidden();
    }

    #[Test]
    public function test_a_user_cannot_show_an_asset_of_another_users_project(): void
    {
        Http::preventStrayRequests();
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = $this->projectFor($owner);
        $asset = Asset::factory()->create(['content_project_id' => $project->id]);

        $this->actingAs($intruder, 'sanctum')
            ->getJson($this->showUrl($project, $asset->id))
            ->assertForbidden();
    }

    #[Test]
    public function test_a_user_cannot_update_an_asset_of_another_users_project(): void
    {
        Http::preventStrayRequests();
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = $this->projectFor($owner);
        $asset = Asset::factory()->create([
            'content_project_id' => $project->id,
            'title' => 'Original title',
        ]);

        $this->actingAs($intruder, 'sanctum')
            ->patchJson($this->showUrl($project, $asset->id), ['title' => 'Hijacked'])
            ->assertForbidden();

        $this->assertSame('Original title', $asset->fresh()->title);
    }

    #[Test]
    public function test_a_user_cannot_delete_an_asset_of_another_users_project(): void
    {
        Http::preventStrayRequests();
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = $this->projectFor($owner);
        $asset = Asset::factory()->create(['content_project_id' => $project->id]);

        $this->actingAs($intruder, 'sanctum')
            ->deleteJson($this->showUrl($project, $asset->id))
            ->assertForbidden();

        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }

    #[Test]
    public function test_a_user_cannot_create_an_asset_in_another_users_project(): void
    {
        Http::preventStrayRequests();
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = $this->projectFor($owner);

        $this->actingAs($intruder, 'sanctum')
            ->postJson($this->listUrl($project), $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('assets', 0);
    }

    #[Test]
    public function test_a_forbidden_response_leaks_no_asset_metadata(): void
    {
        Http::preventStrayRequests();
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = $this->projectFor($owner);
        $asset = Asset::factory()->create([
            'content_project_id' => $project->id,
            'title' => 'Confidential title',
            'description' => 'Confidential description',
            'file_path' => 'app/private/secret.mp4',
            'source_url' => 'https://example.com/private-source',
        ]);

        $response = $this->actingAs($intruder, 'sanctum')
            ->getJson($this->showUrl($project, $asset->id))
            ->assertForbidden();

        $body = $response->getContent();

        $this->assertStringNotContainsString('Confidential title', $body);
        $this->assertStringNotContainsString('Confidential description', $body);
        $this->assertStringNotContainsString('private-source', $body);
        $this->assertStringNotContainsString('app/private/secret.mp4', $body);
        $this->assertNull($response->json('data'));
    }

    #[Test]
    public function test_a_user_cannot_read_an_asset_through_another_users_project_route(): void
    {
        Http::preventStrayRequests();
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $myProject = $this->projectFor($intruder);
        $theirProject = $this->projectFor($owner);
        $theirAsset = Asset::factory()->create(['content_project_id' => $theirProject->id]);

        $this->actingAs($intruder, 'sanctum')
            ->getJson($this->showUrl($myProject, $theirAsset->id))
            ->assertNotFound();
    }

    #[Test]
    public function test_a_project_without_a_creator_denies_everyone(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = ContentProject::factory()->create(['created_by' => null]);
        $asset = Asset::factory()->create(['content_project_id' => $project->id]);

        $this->actingAs($user, 'sanctum')->getJson($this->listUrl($project))->assertForbidden();
        $this->actingAs($user, 'sanctum')->getJson($this->showUrl($project, $asset->id))->assertForbidden();
    }

    #[Test]
    public function test_the_resource_contains_every_documented_field_and_no_file_path(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = Asset::factory()->create([
            'content_project_id' => $project->id,
            'type' => AssetType::Audio,
            'status' => AssetStatus::Available,
            'file_path' => 'app/private/audio/cat-purr.mp3',
            'file_name' => 'cat-purr.mp3',
            'mime_type' => 'audio/mpeg',
            'file_size' => 12_582_912,
            'duration_seconds' => 18,
            'source_url' => 'https://example.com/cat-purr',
            'source_name' => 'Example Library',
            'license_type' => 'cc0',
            'attribution' => 'Public domain',
            'notes' => 'Ambient purr loop.',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->showUrl($project, $asset->id))
            ->assertOk();

        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'content_project_id',
                'type',
                'type_label',
                'status',
                'status_label',
                'title',
                'description',
                'file_name',
                'mime_type',
                'file_size',
                'duration_seconds',
                'width',
                'height',
                'source_url',
                'source_name',
                'license_type',
                'attribution',
                'notes',
                'created_at',
                'updated_at',
            ],
        ]);

        $this->assertArrayNotHasKey('file_path', $response->json('data'));
        $this->assertStringNotContainsString('app/private/audio', $response->getContent());
    }

    #[Test]
    public function test_the_resource_exposes_enum_labels(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = Asset::factory()->create([
            'content_project_id' => $project->id,
            'type' => AssetType::Image,
            'status' => AssetStatus::Pending,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->showUrl($project, $asset->id))
            ->assertOk()
            ->assertJsonPath('data.type', 'image')
            ->assertJsonPath('data.type_label', 'Image')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.status_label', 'Pending');
    }

    #[Test]
    public function test_the_index_does_not_expose_file_paths(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        Asset::factory()->create([
            'content_project_id' => $project->id,
            'file_path' => 'app/private/video/secret.mp4',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project))
            ->assertOk();

        $this->assertStringNotContainsString('file_path', $response->getContent());
        $this->assertStringNotContainsString('app/private/video', $response->getContent());
    }

    #[Test]
    public function test_listing_returns_the_newest_assets_first(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        Asset::factory()->create(['content_project_id' => $project->id, 'title' => 'First']);
        $second = Asset::factory()->create(['content_project_id' => $project->id, 'title' => 'Second']);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->listUrl($project))
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $second->id)
            ->assertJsonPath('data.items.0.title', 'Second');
    }

    #[Test]
    public function test_there_is_no_upload_or_download_endpoint(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = Asset::factory()->create(['content_project_id' => $project->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->showUrl($project, $asset->id).'/download')
            ->assertNotFound();

        $this->actingAs($user, 'sanctum')
            ->postJson($this->listUrl($project).'/upload')
            ->assertNotFound();
    }

    #[Test]
    public function test_there_is_no_status_transition_endpoint(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = Asset::factory()->create(['content_project_id' => $project->id]);

        $this->actingAs($user, 'sanctum')
            ->patchJson($this->showUrl($project, $asset->id).'/status', [
                'status' => AssetStatus::Available->value,
            ])
            ->assertNotFound();

        $this->assertSame(AssetStatus::Pending, $asset->fresh()->status);
    }
}
