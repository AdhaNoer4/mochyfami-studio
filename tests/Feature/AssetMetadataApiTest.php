<?php

namespace Tests\Feature;

use App\Enums\AssetMetadataStatus;
use App\Enums\AssetType;
use App\Exceptions\AssetMetadataException;
use App\Models\Asset;
use App\Models\AssetRequirement;
use App\Models\ContentProject;
use App\Models\User;
use App\Services\AssetFileStorageService;
use App\Services\AssetMetadataService;
use App\Services\Metadata\AssetMetadataInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The analyze and preview endpoints.
 *
 * The preview assertions care about two separate things and it is worth being
 * clear about which is which. That a rightful owner receives their own bytes is
 * a feature. That nobody can reach any other bytes, in any direction, by any
 * means is the security property, and it is what most of this file is about.
 */
class AssetMetadataApiTest extends TestCase
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

    private function disk(): string
    {
        return (string) config('assets.disk');
    }

    private function projectFor(User $user): ContentProject
    {
        return ContentProject::factory()->for($user, 'creator')->create();
    }

    private function analyzeUrl(ContentProject $project, int $assetId): string
    {
        return "/api/v1/projects/{$project->id}/assets/{$assetId}/analyze";
    }

    private function previewUrl(ContentProject $project, int $assetId): string
    {
        return "/api/v1/projects/{$project->id}/assets/{$assetId}/preview";
    }

    /**
     * An asset with a real file of the requested kind sitting on the disk.
     */
    private function assetWithFile(ContentProject $project, AssetType $type, string $name, string $mime, string $contents): Asset
    {
        $path = 'assets/'.$project->id.'/'.$name;
        Storage::disk($this->disk())->put($path, $contents);

        return Asset::factory()->create([
            'content_project_id' => $project->id,
            'type' => $type,
            'file_path' => $path,
            'file_name' => $name,
            'mime_type' => $mime,
            'file_size' => strlen($contents),
        ]);
    }

    /**
     * Replace the metadata service with one whose readers are predictable, so
     * these tests never depend on a decoder being installed.
     */
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

    /**
     * A real PNG, so a preview test transfers real image bytes.
     */
    private function pngBytes(int $width = 8, int $height = 8): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    /*
    |--------------------------------------------------------------------------
    | Analyze
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function analysing_an_asset_publishes_its_technical_metadata(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetWithFile($project, AssetType::Video, 'clip.mp4', 'video/mp4', 'bytes');

        $this->withInspector($this->inspectorReporting(AssetType::Video, [
            'width' => 1080, 'height' => 1920, 'duration_seconds' => 12,
        ]));

        $this->actingAs($user, 'sanctum')
            ->postJson($this->analyzeUrl($project, $asset->id))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.width', 1080)
            ->assertJsonPath('data.height', 1920)
            ->assertJsonPath('data.duration_seconds', 12)
            ->assertJsonPath('data.aspect_ratio', 0.5625)
            ->assertJsonPath('data.metadata_status', 'completed')
            ->assertJsonPath('data.metadata_error', null);
    }

    #[Test]
    public function analysing_an_image_reports_no_duration(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetWithFile($project, AssetType::Image, 'cat.png', 'image/png', $this->pngBytes());

        $this->withInspector($this->inspectorReporting(AssetType::Image, [
            'width' => 1920, 'height' => 1080, 'duration_seconds' => null,
        ]));

        $this->actingAs($user, 'sanctum')
            ->postJson($this->analyzeUrl($project, $asset->id))
            ->assertOk()
            ->assertJsonPath('data.width', 1920)
            ->assertJsonPath('data.duration_seconds', null)
            ->assertJsonPath('data.aspect_ratio', 1.7778);
    }

    #[Test]
    public function an_unreadable_file_is_reported_as_a_failed_status_and_not_as_an_error(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetWithFile($project, AssetType::Video, 'clip.mp4', 'video/mp4', 'bytes');

        $this->withInspector($this->inspectorReporting(
            AssetType::Video,
            ['width' => null, 'height' => null, 'duration_seconds' => null],
            new AssetMetadataException('The stored file could not be read as media.')
        ));

        $response = $this->actingAs($user, 'sanctum')
            ->postJson($this->analyzeUrl($project, $asset->id))
            ->assertOk()
            ->assertJsonPath('data.metadata_status', 'failed')
            ->assertJsonPath('data.width', null)
            ->assertJsonPath('data.height', null)
            ->assertJsonPath('data.aspect_ratio', null);

        // The reason is safe to display: it names the problem, not the file.
        $this->assertStringNotContainsString('storage', $response->getContent());
        $this->assertStringNotContainsString('.mp4', $response->json('data.metadata_error'));
    }

    #[Test]
    public function a_row_pointing_outside_the_asset_directory_is_never_inspected(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        // A path the upload service would never write, aimed at a file that does
        // exist. Analysis must refuse it rather than report this file's
        // dimensions as though they described the asset.
        Storage::disk($this->disk())->put('storage/logs/laravel.log', 'SECRET LOG LINE');

        $asset = Asset::factory()->create([
            'content_project_id' => $project->id,
            'file_path' => 'storage/logs/laravel.log',
            'mime_type' => 'video/mp4',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->analyzeUrl($project, $asset->id))
            ->assertOk()
            ->assertJsonPath('data.metadata_status', 'failed')
            ->assertJsonPath('data.width', null)
            ->assertJsonPath('data.height', null);
    }

    #[Test]
    public function analysing_an_asset_with_no_file_says_so_instead_of_pretending(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = Asset::factory()->create([
            'content_project_id' => $project->id,
            'file_path' => null,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->analyzeUrl($project, $asset->id))
            ->assertOk()
            ->assertJsonPath('data.metadata_status', 'failed');
    }

    #[Test]
    public function analysing_a_row_whose_file_is_gone_is_observable(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetWithFile($project, AssetType::Video, 'clip.mp4', 'video/mp4', 'bytes');

        Storage::disk($this->disk())->delete($asset->file_path);

        $this->actingAs($user, 'sanctum')
            ->postJson($this->analyzeUrl($project, $asset->id))
            ->assertOk()
            ->assertJsonPath('data.metadata_status', 'failed')
            ->assertJsonPath('data.width', null);

        // The row survives a file it cannot read. Losing the asset would be a
        // worse outcome than losing the measurement.
        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }

    #[Test]
    public function analysing_never_touches_type_status_or_requirement_associations(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetWithFile($project, AssetType::Video, 'clip.mp4', 'video/mp4', 'bytes');
        $requirement = AssetRequirement::factory()->create();
        $asset->assetRequirements()->attach($requirement->id);

        $before = [
            'type' => $asset->type,
            'status' => $asset->status,
            'requirement' => $asset->assetRequirements()->count(),
        ];

        $this->withInspector($this->inspectorReporting(AssetType::Video, [
            'width' => 640, 'height' => 480, 'duration_seconds' => 5,
        ]));

        $this->actingAs($user, 'sanctum')
            ->postJson($this->analyzeUrl($project, $asset->id))
            ->assertOk()
            ->assertJsonPath('data.status', $before['status']->value);

        $asset->refresh();

        $this->assertSame($before['type'], $asset->type);
        $this->assertSame($before['status'], $asset->status);
        $this->assertSame($before['requirement'], $asset->assetRequirements()->count());

        // The bytes themselves are untouched too. An inspection is a read, and
        // the file has to still be the file that was uploaded afterwards.
        $this->assertSame('bytes', Storage::disk($this->disk())->get($asset->file_path));
    }

    #[Test]
    public function analysing_an_asset_of_another_project_is_not_found(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $theirProject = $this->projectFor($owner);
        $myProject = $this->projectFor($intruder);

        $asset = $this->assetWithFile($theirProject, AssetType::Video, 'clip.mp4', 'video/mp4', 'bytes');

        // The caller owns the project in the URL and asks for an asset that is
        // not in it. Answered exactly like an id that does not exist.
        $this->actingAs($intruder, 'sanctum')
            ->postJson($this->analyzeUrl($myProject, $asset->id))
            ->assertNotFound();

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'metadata_status' => null]);
    }

    #[Test]
    public function analysing_an_asset_of_another_users_project_is_not_permitted(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $project = $this->projectFor($owner);
        $asset = $this->assetWithFile($project, AssetType::Video, 'clip.mp4', 'video/mp4', 'bytes');

        $this->actingAs($stranger, 'sanctum')
            ->postJson($this->analyzeUrl($project, $asset->id))
            ->assertForbidden();

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'metadata_status' => null]);
    }

    #[Test]
    public function analysing_requires_authentication(): void
    {
        $this->postJson('/api/v1/projects/1/assets/1/analyze')->assertUnauthorized();
    }

    /*
    |--------------------------------------------------------------------------
    | Preview
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function an_owner_can_preview_their_own_asset(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $bytes = $this->pngBytes(16, 12);
        $asset = $this->assetWithFile($project, AssetType::Image, 'cat.png', 'image/png', $bytes);

        $response = $this->actingAs($user, 'sanctum')->get($this->previewUrl($project, $asset->id));

        $response->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertSame($bytes, $response->streamedContent());
    }

    #[Test]
    public function a_video_preview_is_served_inline_and_with_a_stored_content_type(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetWithFile($project, AssetType::Video, 'clip.mp4', 'video/mp4', 'pretend-video-bytes');

        $response = $this->actingAs($user, 'sanctum')->get($this->previewUrl($project, $asset->id));

        $response->assertOk();
        $this->assertSame('video/mp4', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', (string) $response->headers->get('Content-Disposition'));
        // A browser must not be talked into reading these bytes as anything else.
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    #[Test]
    public function a_preview_never_reveals_where_the_file_lives(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetWithFile($project, AssetType::Image, 'cat.png', 'image/png', $this->pngBytes());

        $response = $this->actingAs($user, 'sanctum')->get($this->previewUrl($project, $asset->id));

        $response->assertOk();

        $headers = implode(' ', array_map(
            fn ($value) => is_array($value) ? implode(' ', $value) : (string) $value,
            $response->headers->all()
        ));

        foreach (['storage/app', 'storage\\app', 'private', $asset->file_path] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $headers);
        }
    }

    #[Test]
    public function the_preview_source_cannot_be_redirected_by_a_query_parameter(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        // Something worth stealing, and something the caller must not reach.
        Storage::disk($this->disk())->put('assets/'.$project->id.'/public.png', $this->pngBytes());
        Storage::disk($this->disk())->put('storage/logs/laravel.log', 'SECRET LOG LINE');

        $asset = $this->assetWithFile($project, AssetType::Image, 'mine.png', 'image/png', $this->pngBytes());

        foreach (['path', 'file', 'filename', 'filepath'] as $parameter) {
            foreach (['storage/logs/laravel.log', '../../storage/logs/laravel.log', '/etc/passwd'] as $target) {
                $response = $this->actingAs($user, 'sanctum')
                    ->get($this->previewUrl($project, $asset->id).'?'.$parameter.'='.urlencode($target));

                $response->assertOk();

                $body = $response->streamedContent();

                $this->assertStringNotContainsString('SECRET LOG LINE', $body);
                $this->assertStringNotContainsString('root:', $body);
            }
        }
    }

    #[Test]
    public function a_preview_of_an_asset_of_another_project_is_not_found(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $theirProject = $this->projectFor($owner);
        $myProject = $this->projectFor($intruder);

        $asset = $this->assetWithFile($theirProject, AssetType::Image, 'secret.png', 'image/png', $this->pngBytes());

        $this->actingAs($intruder, 'sanctum')
            ->get($this->previewUrl($myProject, $asset->id))
            ->assertNotFound();
    }

    #[Test]
    public function a_preview_of_another_users_asset_is_not_permitted(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $project = $this->projectFor($owner);
        $asset = $this->assetWithFile($project, AssetType::Image, 'secret.png', 'image/png', $this->pngBytes());

        $this->actingAs($stranger, 'sanctum')
            ->get($this->previewUrl($project, $asset->id))
            ->assertForbidden();
    }

    #[Test]
    public function a_preview_requires_authentication(): void
    {
        $this->get('/api/v1/projects/1/assets/1/preview', ['Accept' => 'application/json'])
            ->assertUnauthorized();
    }

    #[Test]
    public function previewing_an_asset_with_no_file_is_not_found(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = Asset::factory()->create([
            'content_project_id' => $project->id,
            'file_path' => null,
        ]);

        $this->actingAs($user, 'sanctum')
            ->get($this->previewUrl($project, $asset->id))
            ->assertNotFound();
    }

    #[Test]
    public function previewing_an_asset_whose_file_is_gone_is_not_found(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetWithFile($project, AssetType::Image, 'gone.png', 'image/png', $this->pngBytes());

        Storage::disk($this->disk())->delete($asset->file_path);

        $this->actingAs($user, 'sanctum')
            ->get($this->previewUrl($project, $asset->id))
            ->assertNotFound();
    }

    #[Test]
    public function a_row_pointing_outside_the_asset_directory_is_never_served(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        Storage::disk($this->disk())->put('storage/logs/laravel.log', 'SECRET LOG LINE');

        $asset = Asset::factory()->create([
            'content_project_id' => $project->id,
            'file_path' => 'storage/logs/laravel.log',
            'file_name' => 'laravel.log',
            'mime_type' => 'text/plain',
        ]);

        $this->actingAs($user, 'sanctum')
            ->get($this->previewUrl($project, $asset->id))
            ->assertNotFound();
    }

    /** @return array<string, array{string}> */
    public static function unrenderableMimeTypes(): array
    {
        return [
            'html' => ['text/html'],
            'javascript' => ['application/javascript'],
            'svg can carry script' => ['image/svg+xml'],
            'pdf' => ['application/pdf'],
            'plain text' => ['text/plain'],
            'octet stream' => ['application/octet-stream'],
        ];
    }

    #[Test]
    #[DataProvider('unrenderableMimeTypes')]
    public function a_stored_type_a_browser_would_execute_is_never_served_back(string $mimeType): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $asset = $this->assetWithFile($project, AssetType::Other, 'payload.html', $mimeType, '<script>alert(1)</script>');

        $this->actingAs($user, 'sanctum')
            ->get($this->previewUrl($project, $asset->id))
            ->assertNotFound();
    }

    #[Test]
    public function the_resource_publishes_an_application_url_rather_than_a_path(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetWithFile($project, AssetType::Image, 'cat.png', 'image/png', $this->pngBytes());

        $body = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/projects/{$project->id}/assets/{$asset->id}")
            ->assertOk()
            ->getContent();

        // The URL is absolute and JSON-escaped, so it is read back out of the
        // decoded payload rather than matched as raw text.
        $this->assertSame(
            route('assets.preview', ['project' => $project->id, 'asset' => $asset->id]),
            json_decode($body, true)['data']['preview_url']
        );
        $this->assertStringNotContainsString($asset->file_path, $body);
        $this->assertStringNotContainsString('file_path', $body);
        $this->assertStringNotContainsString('storage/app', $body);
    }

    /*
    |--------------------------------------------------------------------------
    | Replacement
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function replacing_a_file_discards_the_previous_files_measurements(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $asset = $this->assetWithFile($project, AssetType::Video, 'landscape.mp4', 'video/mp4', 'old-bytes');

        // The old file was a 1920x1080 thirty second clip.
        $asset->forceFill([
            'width' => 1920,
            'height' => 1080,
            'duration_seconds' => 30,
            'aspect_ratio' => 1.7778,
            'metadata_status' => AssetMetadataStatus::Completed,
            'metadata_analyzed_at' => now(),
        ])->save();

        // The replacement is a portrait clip of a different length.
        $this->withInspector($this->inspectorReporting(AssetType::Video, [
            'width' => 1080, 'height' => 1920, 'duration_seconds' => 10,
        ]));

        $this->actingAs($user, 'sanctum')
            ->post($this->analyzeUrl($project, $asset->id), [], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.width', 1080)
            ->assertJsonPath('data.height', 1920)
            ->assertJsonPath('data.duration_seconds', 10)
            ->assertJsonPath('data.aspect_ratio', 0.5625);

        $asset->refresh();

        $this->assertNotSame(1920, $asset->width);
        $this->assertNotSame(30, $asset->duration_seconds);
    }

    #[Test]
    public function a_replacement_is_pending_analysis_until_it_has_been_measured(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);

        $asset = $this->assetWithFile($project, AssetType::Video, 'clip.mp4', 'video/mp4', 'bytes');

        $asset->forceFill([
            'width' => 1920,
            'height' => 1080,
            'duration_seconds' => 30,
            'aspect_ratio' => 1.7778,
            'metadata_status' => AssetMetadataStatus::Completed,
        ])->save();

        // Automatic analysis is off, so the state after the upload can be seen
        // on its own: the file changed and the measurements have not caught up.
        config(['assets.metadata.analyse_on_upload' => false]);

        $this->actingAs($user, 'sanctum')
            ->post("/api/v1/projects/{$project->id}/assets/{$asset->id}/file", [
                'file' => UploadedFile::fake()->create('next.mp4', 4, 'video/mp4'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.metadata_status', 'pending')
            ->assertJsonPath('data.width', null)
            ->assertJsonPath('data.height', null)
            ->assertJsonPath('data.duration_seconds', null)
            ->assertJsonPath('data.aspect_ratio', null);
    }

    #[Test]
    public function an_upload_measures_the_new_file_without_failing_the_upload(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetWithFile($project, AssetType::Video, 'clip.mp4', 'video/mp4', 'bytes');

        $this->withInspector($this->inspectorReporting(AssetType::Video, [
            'width' => 720, 'height' => 1280, 'duration_seconds' => 8,
        ]));

        $this->actingAs($user, 'sanctum')
            ->post("/api/v1/projects/{$project->id}/assets/{$asset->id}/file", [
                'file' => UploadedFile::fake()->create('next.mp4', 4, 'video/mp4'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.metadata_status', 'completed')
            ->assertJsonPath('data.width', 720)
            ->assertJsonPath('data.aspect_ratio', 0.5625);
    }

    #[Test]
    public function an_upload_whose_file_cannot_be_measured_still_succeeds(): void
    {
        $user = User::factory()->create();
        $project = $this->projectFor($user);
        $asset = $this->assetWithFile($project, AssetType::Video, 'clip.mp4', 'video/mp4', 'bytes');

        $this->withInspector($this->inspectorReporting(
            AssetType::Video,
            ['width' => null, 'height' => null, 'duration_seconds' => null],
            new AssetMetadataException('The stored file could not be read as media.')
        ));

        $this->actingAs($user, 'sanctum')
            ->post("/api/v1/projects/{$project->id}/assets/{$asset->id}/file", [
                'file' => UploadedFile::fake()->create('next.mp4', 4, 'video/mp4'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.metadata_status', 'failed');
    }
}
