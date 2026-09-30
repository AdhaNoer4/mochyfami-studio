<?php

namespace Tests\Feature;

use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\AssetRequirement;
use App\Models\ContentProject;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class AssetFileUploadApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake((string) config('assets.disk'));
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function projectFor(User $user): ContentProject
    {
        return ContentProject::factory()->for($user, 'creator')->create();
    }

    private function uploadUrl(ContentProject $project, int $assetId): string
    {
        return "/api/v1/projects/{$project->id}/assets/{$assetId}/file";
    }

    private function assetOf(ContentProject $project, AssetType $type): Asset
    {
        return Asset::factory()->create([
            'content_project_id' => $project->id,
            'type' => $type,
            'file_path' => null,
            'file_name' => null,
            'mime_type' => null,
            'file_size' => null,
        ]);
    }

    private function imageFile(string $name = 'cat.png'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 4, 'image/png');
    }

    private function videoFile(string $name = 'cat.mp4'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 4, 'video/mp4');
    }

    private function audioFile(string $name = 'meow.mp3'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 4, 'audio/mpeg');
    }

    /** @return array<int, string> */
    private function storedFiles(): array
    {
        return Storage::disk((string) config('assets.disk'))->allFiles();
    }

    /**
     * Make every write to the disk report a failure.
     *
     * This is the only honest way to test the failure path: a real write
     * failure would depend on the machine's file permissions, and on Windows
     * those do not behave the way they do on Linux. The real disk is handed
     * back so the test can still assert against the files that are really
     * there, which is the whole point of the test.
     */
    private function breakTheWrite(): Filesystem
    {
        $real = Storage::disk((string) config('assets.disk'));

        $broken = Mockery::mock(Filesystem::class);
        $broken->shouldReceive('putFileAs')->andReturn(false);

        Storage::shouldReceive('disk')->andReturn($broken);

        return $real;
    }

    /**
     * Make the disk find the file and then fail to remove it, which is the
     * failure that matters on delete: the file is genuinely there and genuinely
     * cannot be deleted.
     */
    private function breakTheDelete(): Filesystem
    {
        $real = Storage::disk((string) config('assets.disk'));

        $broken = Mockery::mock(Filesystem::class);
        $broken->shouldReceive('exists')->andReturn(true);
        $broken->shouldReceive('delete')->andReturn(false);

        Storage::shouldReceive('disk')->andReturn($broken);

        return $real;
    }

    /*
    |--------------------------------------------------------------------------
    | Group A - upload success
    |--------------------------------------------------------------------------
    */

    #[Test]
    #[DataProvider('successfulUploadProvider')]
    public function test_a_compatible_file_is_stored_and_recorded(AssetType $type, UploadedFile $file, string $mimeType): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetOf($project, $type);

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), ['file' => $file])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mime_type', $mimeType)
            ->assertJsonPath('data.file_size', 4096);

        $fresh = $asset->fresh();

        $this->assertNotNull($fresh->file_path);
        $this->assertSame($file->getClientOriginalName(), $fresh->file_name);
        $this->assertSame($mimeType, $fresh->mime_type);
        $this->assertSame(4096, $fresh->file_size);

        Storage::disk((string) config('assets.disk'))->assertExists($fresh->file_path);
    }

    /**
     * @return array<string, array{0: AssetType, 1: UploadedFile, 2: string}>
     */
    public static function successfulUploadProvider(): array
    {
        return [
            'image' => [AssetType::Image, UploadedFile::fake()->create('cat.png', 4, 'image/png'), 'image/png'],
            'video' => [AssetType::Video, UploadedFile::fake()->create('cat.mp4', 4, 'video/mp4'), 'video/mp4'],
            'audio' => [AssetType::Audio, UploadedFile::fake()->create('meow.mp3', 4, 'audio/mpeg'), 'audio/mpeg'],
        ];
    }

    #[Test]
    public function test_the_response_never_exposes_the_storage_path(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetOf($project, AssetType::Video);

        $response = $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), ['file' => $this->videoFile()])
            ->assertOk();

        $storedPath = $asset->fresh()->file_path;

        $this->assertArrayNotHasKey('file_path', $response->json('data'));
        $this->assertStringNotContainsString($storedPath, $response->getContent());
        $this->assertStringNotContainsString('storage/app', $response->getContent());
        $this->assertStringNotContainsString('assets/'.$project->id, $response->getContent());
    }

    #[Test]
    public function test_uploading_does_not_change_the_type_or_the_status(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetOf($project, AssetType::Video);

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), ['file' => $this->videoFile()])
            ->assertOk()
            ->assertJsonPath('data.type', 'video')
            ->assertJsonPath('data.status', 'pending');

        $fresh = $asset->fresh();

        $this->assertSame(AssetType::Video, $fresh->type);
        $this->assertSame('pending', $fresh->status->value);
    }

    #[Test]
    public function test_uploading_does_not_fulfil_or_alter_a_requirement_association(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetOf($project, AssetType::Video);
        $requirement = AssetRequirement::factory()->create();

        $requirement->assets()->attach($asset);

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), ['file' => $this->videoFile()])
            ->assertOk();

        $this->assertDatabaseHas('asset_requirement_asset', [
            'asset_requirement_id' => $requirement->id,
            'asset_id' => $asset->id,
        ]);
        $this->assertSame('pending', $requirement->fresh()->status->value);
    }

    /*
    |--------------------------------------------------------------------------
    | Group B - type mismatch
    |--------------------------------------------------------------------------
    */

    #[Test]
    #[DataProvider('mismatchProvider')]
    public function test_a_file_the_asset_type_cannot_use_is_rejected(
        AssetType $type,
        UploadedFile $file
    ): void {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetOf($project, $type);

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), ['file' => $file])
            ->assertStatus(422);

        $fresh = $asset->fresh();

        $this->assertNull($fresh->file_path);
        $this->assertNull($fresh->file_size);
        $this->assertSame([], $this->storedFiles());
    }

    /**
     * @return array<string, array{0: AssetType, 1: UploadedFile}>
     */
    public static function mismatchProvider(): array
    {
        return [
            'a video into an image' => [AssetType::Image, UploadedFile::fake()->create('clip.mp4', 4, 'video/mp4')],
            'an image into a video' => [AssetType::Video, UploadedFile::fake()->create('cat.png', 4, 'image/png')],
            'an image into audio' => [AssetType::Audio, UploadedFile::fake()->create('cat.png', 4, 'image/png')],
            'audio into a video' => [AssetType::Video, UploadedFile::fake()->create('meow.mp3', 4, 'audio/mpeg')],
            'a video into other' => [AssetType::Other, UploadedFile::fake()->create('clip.mp4', 4, 'video/mp4')],
            'an image into other' => [AssetType::Other, UploadedFile::fake()->create('cat.png', 4, 'image/png')],
            'a pdf into other' => [AssetType::Other, UploadedFile::fake()->create('notes.pdf', 4, 'application/pdf')],
        ];
    }

    #[Test]
    public function test_the_mismatch_message_names_the_detected_type_and_the_asset_type(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetOf($project, AssetType::Image);

        $response = $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), ['file' => $this->videoFile()])
            ->assertStatus(422);

        $this->assertStringContainsString('video/mp4', $response->json('message'));
        $this->assertStringContainsString('Image', $response->json('message'));
    }

    #[Test]
    public function test_the_other_type_says_it_accepts_no_formats_rather_than_listing_any(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetOf($project, AssetType::Other);

        $response = $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), ['file' => $this->imageFile()])
            ->assertStatus(422);

        $this->assertStringContainsString('does not accept file uploads', $response->json('message'));
    }

    #[Test]
    public function test_a_file_that_is_not_media_at_all_is_rejected_by_validation(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetOf($project, AssetType::Video);

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), [
                'file' => UploadedFile::fake()->create('payload.php', 4, 'text/x-php'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertNull($asset->fresh()->file_path);
        $this->assertSame([], $this->storedFiles());
    }

    #[Test]
    public function test_a_request_without_a_file_is_rejected(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetOf($project, AssetType::Video);

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertSame([], $this->storedFiles());
    }

    /*
    |--------------------------------------------------------------------------
    | Group C - file size
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_a_file_at_the_limit_is_accepted(): void
    {
        Http::preventStrayRequests();
        config(['assets.upload.max_size_kb' => 4]);
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetOf($project, AssetType::Video);

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), ['file' => $this->videoFile()])
            ->assertOk();

        Storage::disk((string) config('assets.disk'))->assertExists($asset->fresh()->file_path);
    }

    #[Test]
    public function test_a_file_over_the_limit_is_rejected_and_nothing_is_stored(): void
    {
        Http::preventStrayRequests();
        config(['assets.upload.max_size_kb' => 1]);
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetOf($project, AssetType::Video);

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), [
                'file' => UploadedFile::fake()->create('long.mp4', 8, 'video/mp4'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertNull($asset->fresh()->file_path);
        $this->assertSame([], $this->storedFiles());
    }

    #[Test]
    public function test_the_limit_is_configured_rather_than_hard_coded(): void
    {
        $this->assertSame(524288, config('assets.upload.max_size_kb'));
    }

    /*
    |--------------------------------------------------------------------------
    | Group D - security
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_the_upload_endpoint_requires_authentication(): void
    {
        Http::preventStrayRequests();

        $this->post('/api/v1/projects/1/assets/1/file', [
            'file' => $this->videoFile(),
        ], ['Accept' => 'application/json'])->assertUnauthorized();
    }

    #[Test]
    public function test_a_stranger_cannot_upload_to_someone_elses_asset(): void
    {
        Http::preventStrayRequests();
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = $this->projectFor($owner);
        $asset = $this->assetOf($project, AssetType::Video);

        $this->actingAs($intruder, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), ['file' => $this->videoFile()])
            ->assertForbidden();

        $this->assertNull($asset->fresh()->file_path);
        $this->assertSame([], $this->storedFiles());
    }

    #[Test]
    public function test_an_asset_of_another_project_of_the_same_owner_is_not_found(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $foreign = $this->assetOf($this->projectFor($user), AssetType::Video);

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $foreign->id), ['file' => $this->videoFile()])
            ->assertNotFound();

        $this->assertNull($foreign->fresh()->file_path);
        $this->assertSame([], $this->storedFiles());
    }

    #[Test]
    public function test_an_asset_of_another_users_project_is_not_found(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $victim = User::factory()->create();
        $project = $this->projectFor($user);
        $foreign = $this->assetOf($this->projectFor($victim), AssetType::Video);

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $foreign->id), ['file' => $this->videoFile()])
            ->assertNotFound();

        $this->assertNull($foreign->fresh()->file_path);
        $this->assertSame([], $this->storedFiles());
    }

    #[Test]
    public function test_a_client_supplied_path_type_and_size_are_all_ignored(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetOf($project, AssetType::Video);

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), [
                'file' => $this->videoFile(),
                'file_path' => '../../../storage/app/private/.env',
                'mime_type' => 'application/x-php',
                'file_size' => 1,
                'status' => 'approved',
            ])
            ->assertOk()
            ->assertJsonPath('data.mime_type', 'video/mp4')
            ->assertJsonPath('data.file_size', 4096)
            ->assertJsonPath('data.status', 'pending');

        $fresh = $asset->fresh();

        $this->assertStringStartsWith("assets/{$project->id}/", $fresh->file_path);
        $this->assertStringNotContainsString('.env', $fresh->file_path);
    }

    #[Test]
    #[DataProvider('hostileFilenameProvider')]
    public function test_a_hostile_filename_cannot_escape_the_asset_directory(
        string $submitted,
        string $expectedDisplayName
    ): void {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetOf($project, AssetType::Video);

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), [
                'file' => UploadedFile::fake()->create($submitted, 4, 'video/mp4'),
            ])
            ->assertOk();

        $fresh = $asset->fresh();

        $this->assertStringStartsWith("assets/{$project->id}/", $fresh->file_path);
        $this->assertStringNotContainsString('..', $fresh->file_path);
        $this->assertStringNotContainsString('\\', $fresh->file_path);
        $this->assertStringNotContainsString('evil', $fresh->file_path);
        $this->assertSame($expectedDisplayName, $fresh->file_name);

        Storage::disk((string) config('assets.disk'))->assertExists($fresh->file_path);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function hostileFilenameProvider(): array
    {
        return [
            'posix traversal' => ['../../../evil.mp4', 'evil.mp4'],
            'windows traversal' => ['..\\..\\..\\evil.mp4', 'evil.mp4'],
            'a windows absolute path' => ['C:\\Windows\\evil.mp4', 'evil.mp4'],
            'a unc path' => ['\\\\server\\share\\evil.mp4', 'evil.mp4'],
            'a posix absolute path' => ['/etc/evil.mp4', 'evil.mp4'],
            'a null byte' => ["evil\0.mp4", 'evil.mp4'],
        ];
    }

    #[Test]
    public function test_the_stored_path_is_project_scoped_uuid_named_and_appropriately_extended(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetOf($project, AssetType::Video);

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), ['file' => $this->videoFile()])
            ->assertOk();

        $this->assertMatchesRegularExpression(
            '/^assets\/'.$project->id.'\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.mp4$/',
            $asset->fresh()->file_path
        );
    }

    #[Test]
    public function test_two_uploads_to_different_assets_never_share_a_path(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $first = $this->assetOf($project, AssetType::Video);
        $second = $this->assetOf($project, AssetType::Video);

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $first->id), ['file' => $this->videoFile()])
            ->assertOk();
        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $second->id), ['file' => $this->videoFile()])
            ->assertOk();

        $firstPath = $first->fresh()->file_path;
        $secondPath = $second->fresh()->file_path;

        $this->assertNotSame($firstPath, $secondPath);
        $this->assertCount(2, $this->storedFiles());

        Storage::disk((string) config('assets.disk'))->assertExists([$firstPath, $secondPath]);
    }

    #[Test]
    public function test_uploading_to_one_asset_leaves_another_assets_file_untouched(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $untouched = $this->assetOf($project, AssetType::Video);
        $other = $this->assetOf($project, AssetType::Video);

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $untouched->id), ['file' => $this->videoFile('keep.mp4')])
            ->assertOk();

        $untouchedPath = $untouched->fresh()->file_path;

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $other->id), ['file' => $this->videoFile('other.mp4')])
            ->assertOk();

        Storage::disk((string) config('assets.disk'))->assertExists($untouchedPath);
        $this->assertSame($untouchedPath, $untouched->fresh()->file_path);
    }

    /*
    |--------------------------------------------------------------------------
    | Group E - replacement
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_replacing_a_file_swaps_the_path_and_removes_the_old_one(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetOf($project, AssetType::Video);

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), ['file' => $this->videoFile('first.mp4')])
            ->assertOk();

        $oldPath = $asset->fresh()->file_path;

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), [
                'file' => $this->videoFile('second.mp4'),
            ])
            ->assertOk()
            ->assertJsonPath('data.file_name', 'second.mp4');

        $newPath = $asset->fresh()->file_path;
        $disk = Storage::disk((string) config('assets.disk'));

        $this->assertNotSame($oldPath, $newPath);
        $disk->assertExists($newPath);
        $disk->assertMissing($oldPath);
        $this->assertCount(1, $this->storedFiles());
    }

    #[Test]
    public function test_a_failed_write_during_a_replacement_leaves_the_old_file_and_the_row_alone(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetOf($project, AssetType::Video);

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), ['file' => $this->videoFile('first.mp4')])
            ->assertOk();

        $oldPath = $asset->fresh()->file_path;
        $disk = $this->breakTheWrite();

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), ['file' => $this->videoFile('second.mp4')])
            ->assertStatus(500)
            ->assertJsonPath('success', false);

        $disk->assertExists($oldPath);
        $this->assertSame($oldPath, $asset->fresh()->file_path);
        $this->assertSame('first.mp4', $asset->fresh()->file_name);
    }

    #[Test]
    public function test_a_failed_database_update_removes_the_new_file_and_keeps_the_old_one(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetOf($project, AssetType::Video);

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), ['file' => $this->videoFile('first.mp4')])
            ->assertOk();

        $oldPath = $asset->fresh()->file_path;
        $filesBefore = $this->storedFiles();

        // Fails the save the replacement makes, which is the moment the new
        // file exists but no metadata points at it. The listener is bound to
        // this test's event dispatcher, so it cannot reach any other test.
        Asset::saving(function (): void {
            throw new RuntimeException('the database is unavailable');
        });

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), ['file' => $this->videoFile('second.mp4')])
            ->assertStatus(500);

        $disk = Storage::disk((string) config('assets.disk'));

        $disk->assertExists($oldPath);
        $this->assertSame($filesBefore, $this->storedFiles());
        $this->assertSame($oldPath, $asset->fresh()->file_path);
        $this->assertSame('first.mp4', $asset->fresh()->file_name);
    }

    #[Test]
    public function test_a_replacement_never_leaves_the_asset_looking_like_it_has_no_file(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetOf($project, AssetType::Audio);

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), ['file' => $this->audioFile('one.mp3')])
            ->assertOk();

        $firstPath = $asset->fresh()->file_path;

        // An incompatible replacement is refused before anything is written,
        // so the asset keeps the file it already had rather than losing it.
        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), ['file' => $this->videoFile('nope.mp4')])
            ->assertStatus(422);

        Storage::disk((string) config('assets.disk'))->assertExists($firstPath);
        $this->assertSame($firstPath, $asset->fresh()->file_path);
    }

    /*
    |--------------------------------------------------------------------------
    | Group F - delete cleanup
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_deleting_an_asset_removes_its_file(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetOf($project, AssetType::Video);

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), ['file' => $this->videoFile()])
            ->assertOk();

        $path = $asset->fresh()->file_path;

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/assets/{$asset->id}")
            ->assertOk();

        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
        Storage::disk((string) config('assets.disk'))->assertMissing($path);
        $this->assertSame([], $this->storedFiles());
    }

    #[Test]
    public function test_deleting_an_asset_without_a_file_does_not_fail(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetOf($project, AssetType::Video);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/assets/{$asset->id}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
        $this->assertSame([], $this->storedFiles());
    }

    #[Test]
    public function test_deleting_one_asset_keeps_the_files_of_the_others(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $kept = $this->assetOf($project, AssetType::Video);
        $removed = $this->assetOf($project, AssetType::Video);

        foreach ([$kept, $removed] as $asset) {
            $this->actingAs($user, 'sanctum')
                ->post($this->uploadUrl($project, $asset->id), ['file' => $this->videoFile()])
                ->assertOk();
        }

        $keptPath = $kept->fresh()->file_path;
        $removedPath = $removed->fresh()->file_path;

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/assets/{$removed->id}")
            ->assertOk();

        $disk = Storage::disk((string) config('assets.disk'));

        $disk->assertExists($keptPath);
        $disk->assertMissing($removedPath);
    }

    #[Test]
    public function test_a_failed_delete_still_removes_the_row_rather_than_lying_about_the_file(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetOf($project, AssetType::Video);

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), ['file' => $this->videoFile()])
            ->assertOk();

        $path = $asset->fresh()->file_path;
        $disk = $this->breakTheDelete();

        // deleteQuietly() reports the storage failure and lets the delete
        // finish. The row is gone, so nothing claims to have a file.
        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/projects/{$project->id}/assets/{$asset->id}")
            ->assertOk();

        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
        $disk->assertExists($path);
    }

    /*
    |--------------------------------------------------------------------------
    | Group G - MIME detection from real contents
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function test_the_stored_type_is_read_from_the_contents_rather_than_the_filename(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetOf($project, AssetType::Image);

        // A genuine PNG, submitted under a video name. A faked upload file
        // reports whatever MIME it is handed, so this one is written to disk
        // for real and read back through the same detection the application
        // uses in production.
        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), [
                'file' => $this->realFileContainingPngBytes('disguised.mp4'),
            ])
            ->assertOk()
            ->assertJsonPath('data.mime_type', 'image/png');

        $this->assertStringEndsWith('.png', $asset->fresh()->file_path);
    }

    #[Test]
    public function test_a_png_disguised_as_a_video_is_refused_by_a_video_asset(): void
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetOf($project, AssetType::Video);

        $this->actingAs($user, 'sanctum')
            ->post($this->uploadUrl($project, $asset->id), [
                'file' => $this->realFileContainingPngBytes('disguised.mp4'),
            ])
            ->assertStatus(422);

        $this->assertNull($asset->fresh()->file_path);
        $this->assertSame([], $this->storedFiles());
    }

    /**
     * A real one pixel PNG on a real temporary file, with no client MIME type
     * given at all, so finfo has nothing to go on but the bytes.
     */
    private function realFileContainingPngBytes(string $submittedName): UploadedFile
    {
        $bytes = (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        $path = tempnam(sys_get_temp_dir(), 'asset-upload');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $submittedName, null, null, true);
    }
}
