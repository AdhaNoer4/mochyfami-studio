<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\ContentProject;
use App\Models\User;
use App\Services\AssetService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssetTest extends TestCase
{
    use RefreshDatabase;

    private function projectFor(User $user): ContentProject
    {
        return ContentProject::factory()->for($user, 'creator')->create();
    }

    public function test_an_asset_belongs_to_the_project_it_was_created_under(): void
    {
        $project = $this->projectFor(User::factory()->create());

        $asset = Asset::factory()->create(['content_project_id' => $project->id]);

        $this->assertSame($project->id, $asset->project->id);
        $this->assertTrue($project->assets->contains($asset));
    }

    public function test_a_project_without_assets_has_an_empty_collection(): void
    {
        $project = $this->projectFor(User::factory()->create());

        $this->assertCount(0, $project->assets);
        $this->assertSame(0, $project->assets()->count());
    }

    public function test_a_new_asset_defaults_to_a_pending_video_without_any_file_metadata(): void
    {
        $project = $this->projectFor(User::factory()->create());

        $asset = Asset::factory()->create(['content_project_id' => $project->id]);

        $this->assertSame(AssetType::Video, $asset->type);
        $this->assertSame(AssetStatus::Pending, $asset->status);
        $this->assertNull($asset->file_path);
        $this->assertNull($asset->file_name);
        $this->assertNull($asset->mime_type);
        $this->assertNull($asset->file_size);
        $this->assertNull($asset->duration_seconds);
    }

    public function test_type_and_status_are_cast_to_their_enums(): void
    {
        $asset = Asset::factory()->create([
            'type' => AssetType::Audio,
            'status' => AssetStatus::Approved,
        ]);

        $fresh = $asset->fresh();

        $this->assertInstanceOf(AssetType::class, $fresh->type);
        $this->assertInstanceOf(AssetStatus::class, $fresh->status);
        $this->assertSame(AssetType::Audio, $fresh->type);
        $this->assertSame(AssetStatus::Approved, $fresh->status);
    }

    public function test_numeric_metadata_is_cast_to_integers(): void
    {
        $asset = Asset::factory()->create([
            'file_size' => '2048',
            'duration_seconds' => '12',
            'width' => '1080',
            'height' => '1920',
        ])->fresh();

        $this->assertSame(2048, $asset->file_size);
        $this->assertSame(12, $asset->duration_seconds);
        $this->assertSame(1080, $asset->width);
        $this->assertSame(1920, $asset->height);
    }

    public function test_every_metadata_column_may_be_null(): void
    {
        $asset = Asset::factory()->create([
            'type' => AssetType::Other,
            'title' => null,
            'description' => null,
            'file_path' => null,
            'file_name' => null,
            'mime_type' => null,
            'file_size' => null,
            'duration_seconds' => null,
            'width' => null,
            'height' => null,
            'source_url' => null,
            'source_name' => null,
            'license_type' => null,
            'attribution' => null,
            'notes' => null,
        ])->fresh();

        $this->assertSame(AssetType::Other, $asset->type);
        $this->assertNull($asset->title);
        $this->assertNull($asset->source_url);
        $this->assertNull($asset->notes);
    }

    public function test_a_project_id_is_required_by_the_foreign_key(): void
    {
        $this->expectException(QueryException::class);

        Asset::factory()->create(['content_project_id' => 999999]);
    }

    public function test_deleting_a_project_cascades_to_its_assets(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $asset = Asset::factory()->create(['content_project_id' => $project->id]);

        $project->delete();

        $this->assertNull(Asset::find($asset->id));
    }

    public function test_deleting_one_asset_leaves_the_others_alone(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $keep = Asset::factory()->create(['content_project_id' => $project->id]);
        $remove = Asset::factory()->create(['content_project_id' => $project->id]);

        $remove->delete();

        $this->assertNull(Asset::find($remove->id));
        $this->assertNotNull(Asset::find($keep->id));
    }

    public function test_the_service_lists_only_the_assets_of_the_given_project(): void
    {
        $user = User::factory()->create();
        $mine = $this->projectFor($user);
        $theirs = ContentProject::factory()->create();

        Asset::factory()->create(['content_project_id' => $mine->id]);
        Asset::factory()->count(2)->create(['content_project_id' => $theirs->id]);

        $listed = app(AssetService::class)->paginateAssets($mine);

        $this->assertSame(1, $listed->total());
        $this->assertCount(1, $listed->items());
        $this->assertSame($mine->id, $listed->items()[0]->content_project_id);
    }

    public function test_the_service_creates_metadata_only_and_forces_the_pending_status(): void
    {
        $project = $this->projectFor(User::factory()->create());

        $asset = app(AssetService::class)->createAsset($project, [
            'type' => AssetType::Image,
            'status' => AssetStatus::Approved,
            'title' => 'Cat walking',
            'source_url' => 'https://example.com/cat.jpg',
        ]);

        $this->assertSame($project->id, $asset->content_project_id);
        $this->assertSame(AssetType::Image, $asset->type);
        $this->assertSame(AssetStatus::Pending, $asset->status);
        $this->assertNull($asset->file_path);
    }

    public function test_the_service_ignores_a_client_supplied_file_path(): void
    {
        $project = $this->projectFor(User::factory()->create());

        $asset = app(AssetService::class)->createAsset($project, [
            'type' => AssetType::Video,
            'file_path' => '../../../../etc/passwd',
        ]);

        $this->assertNull($asset->file_path);
    }

    public function test_updating_an_asset_cannot_change_its_status(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $asset = Asset::factory()->create(['content_project_id' => $project->id]);

        $updated = app(AssetService::class)->updateAsset($asset, [
            'title' => 'Renamed',
            'status' => AssetStatus::Archived,
        ]);

        $this->assertSame('Renamed', $updated->title);
        $this->assertSame(AssetStatus::Pending, $updated->status);
    }

    public function test_updating_an_asset_ignores_a_client_supplied_file_path(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $asset = Asset::factory()->create([
            'content_project_id' => $project->id,
            'file_path' => 'assets/original.mp4',
        ]);

        $updated = app(AssetService::class)->updateAsset($asset, [
            'file_path' => '/tmp/elsewhere.mp4',
        ]);

        $this->assertSame('assets/original.mp4', $updated->file_path);
    }

    public function test_updating_an_asset_changes_only_the_fields_it_was_given(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $asset = Asset::factory()->create([
            'content_project_id' => $project->id,
            'title' => 'Original title',
            'description' => 'Original description',
            'source_name' => 'Original source',
        ]);

        $updated = app(AssetService::class)->updateAsset($asset, ['title' => 'New title']);

        $this->assertSame('New title', $updated->title);
        $this->assertSame('Original description', $updated->description);
        $this->assertSame('Original source', $updated->source_name);
    }

    public function test_updating_an_asset_can_null_a_optional_field(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $asset = Asset::factory()->create([
            'content_project_id' => $project->id,
            'license_type' => 'cc-by',
        ]);

        $this->assertNull(app(AssetService::class)->updateAsset($asset, ['license_type' => null])->license_type);
    }

    public function test_finding_an_asset_of_another_project_throws_not_found(): void
    {
        $mine = $this->projectFor(User::factory()->create());
        $theirs = ContentProject::factory()->create();
        $foreign = Asset::factory()->create(['content_project_id' => $theirs->id]);

        $this->expectException(ModelNotFoundException::class);

        app(AssetService::class)->findAsset($mine, $foreign->id);
    }

    public function test_finding_an_unknown_asset_throws_not_found(): void
    {
        $project = $this->projectFor(User::factory()->create());

        $this->expectException(ModelNotFoundException::class);

        app(AssetService::class)->findAsset($project, 999999);
    }

    public function test_deleting_through_the_service_removes_the_row(): void
    {
        $project = $this->projectFor(User::factory()->create());
        $asset = Asset::factory()->create(['content_project_id' => $project->id]);

        app(AssetService::class)->deleteAsset($asset);

        $this->assertNull(Asset::find($asset->id));
    }

    public function test_creating_an_asset_writes_nothing_to_disk(): void
    {
        Storage::shouldReceive('disk')->never();
        Http::preventStrayRequests();

        $project = $this->projectFor(User::factory()->create());

        app(AssetService::class)->createAsset($project, [
            'type' => AssetType::Video,
            'source_url' => 'https://example.com/never-fetched.mp4',
        ]);

        $this->assertDatabaseHas('assets', ['content_project_id' => $project->id]);
    }

    #[DataProvider('assetTypeProvider')]
    public function test_every_asset_type_can_be_persisted(AssetType $type): void
    {
        $project = $this->projectFor(User::factory()->create());

        $asset = Asset::factory()->create([
            'content_project_id' => $project->id,
            'type' => $type,
        ]);

        $this->assertSame($type, $asset->fresh()->type);
    }

    public static function assetTypeProvider(): array
    {
        return [
            'video' => [AssetType::Video],
            'image' => [AssetType::Image],
            'audio' => [AssetType::Audio],
            'other' => [AssetType::Other],
        ];
    }

    #[DataProvider('assetStatusProvider')]
    public function test_every_asset_status_can_be_stored(AssetStatus $status): void
    {
        $project = $this->projectFor(User::factory()->create());

        $asset = Asset::factory()->create([
            'content_project_id' => $project->id,
            'status' => $status,
        ]);

        $this->assertSame($status, $asset->fresh()->status);
    }

    public static function assetStatusProvider(): array
    {
        return [
            'pending' => [AssetStatus::Pending],
            'available' => [AssetStatus::Available],
            'processing' => [AssetStatus::Processing],
            'approved' => [AssetStatus::Approved],
            'rejected' => [AssetStatus::Rejected],
            'archived' => [AssetStatus::Archived],
        ];
    }

    public function test_an_asset_cannot_be_mass_assigned_into_another_project(): void
    {
        $mine = $this->projectFor(User::factory()->create());
        $theirs = ContentProject::factory()->create();

        $asset = Asset::factory()->create(['content_project_id' => $mine->id]);

        $asset->update(['content_project_id' => $theirs->id]);

        $this->assertSame($mine->id, $asset->fresh()->content_project_id);
    }

    public function test_an_asset_has_no_requirement_relationship_yet(): void
    {
        $asset = Asset::factory()->create();

        $this->assertFalse(method_exists($asset, 'assetRequirements'));
        $this->assertFalse(method_exists($asset, 'requirements'));
    }
}
