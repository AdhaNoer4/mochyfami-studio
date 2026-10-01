<?php

namespace Tests\Unit;

use App\Enums\AssetMetadataStatus;
use App\Enums\AssetType;
use App\Exceptions\AssetMetadataException;
use App\Models\Asset;
use App\Models\ContentProject;
use App\Services\AssetFileStorageService;
use App\Services\AssetMetadataService;
use App\Services\Metadata\AssetMetadataInspector;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The behaviour of the analysis pipeline, with the readers replaced.
 *
 * These tests decide what gets persisted and what state an asset is left in.
 * They deliberately do not run a real decoder: what FFmpeg or GD actually say
 * about a file is tested separately, in the inspector tests, and mixing the
 * two here would make every assertion about persistence depend on an external
 * binary being installed.
 */
class AssetMetadataServiceTest extends TestCase
{
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

    private function serviceWith(AssetMetadataInspector ...$inspectors): AssetMetadataService
    {
        return new AssetMetadataService(
            new AssetFileStorageService,
            array_values($inspectors)
        );
    }

    /**
     * An inspector that returns whatever it was told to, and remembers the
     * path it was handed so a test can assert where the bytes came from.
     */
    private function inspectorReturning(
        AssetType $type,
        array $result,
        ?AssetMetadataException $failure = null,
        ?string &$seenPath = null
    ): AssetMetadataInspector {
        return new class($type, $result, $failure, $seenPath) implements AssetMetadataInspector
        {
            public ?string $seenMime = null;

            public function __construct(
                private AssetType $type,
                private array $result,
                private ?AssetMetadataException $failure,
                private ?string &$seenPath
            ) {}

            public function handles(): AssetType
            {
                return $this->type;
            }

            public function inspect(string $absolutePath, string $mimeType): array
            {
                $this->seenMime = $mimeType;
                $this->seenPath = $absolutePath;

                if ($this->failure !== null) {
                    throw $this->failure;
                }

                return $this->result;
            }
        };
    }

    private function attachedAsset(AssetType $type, string $storedPath = 'assets/9/clip.mp4', string $mime = 'video/mp4'): Asset
    {
        Storage::disk((string) config('assets.disk'))->put($storedPath, 'bytes');

        return Asset::factory()->create([
            'content_project_id' => ContentProject::factory()->create()->id,
            'type' => $type,
            'file_path' => $storedPath,
            'file_name' => 'clip.'.pathinfo($storedPath, PATHINFO_EXTENSION),
            'mime_type' => $mime,
            'file_size' => 5,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Per-type extraction
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function an_image_records_its_dimensions_and_never_a_duration(): void
    {
        $asset = $this->attachedAsset(AssetType::Image, 'assets/9/cat.png', 'image/png');

        $this->serviceWith($this->inspectorReturning(AssetType::Image, [
            'width' => 1920,
            'height' => 1080,
            'duration_seconds' => null,
        ]))->analyse($asset);

        $asset->refresh();

        $this->assertSame(1920, $asset->width);
        $this->assertSame(1080, $asset->height);
        $this->assertNull($asset->duration_seconds, 'An image has no duration to report.');
        $this->assertSame(1.7778, (float) $asset->aspect_ratio);
        $this->assertSame(AssetMetadataStatus::Completed, $asset->metadata_status);
        $this->assertNull($asset->metadata_error);
        $this->assertNotNull($asset->metadata_analyzed_at);
    }

    #[Test]
    public function a_video_records_its_dimensions_and_duration(): void
    {
        $asset = $this->attachedAsset(AssetType::Video);

        $this->serviceWith($this->inspectorReturning(AssetType::Video, [
            'width' => 1080,
            'height' => 1920,
            'duration_seconds' => 12,
        ]))->analyse($asset);

        $asset->refresh();

        $this->assertSame(1080, $asset->width);
        $this->assertSame(1920, $asset->height);
        $this->assertSame(12, $asset->duration_seconds);
        $this->assertSame(0.5625, (float) $asset->aspect_ratio);
        $this->assertSame(AssetMetadataStatus::Completed, $asset->metadata_status);
    }

    #[Test]
    public function audio_records_a_duration_and_never_invents_dimensions(): void
    {
        $asset = $this->attachedAsset(AssetType::Audio, 'assets/9/meow.mp3', 'audio/mpeg');

        $this->serviceWith($this->inspectorReturning(AssetType::Audio, [
            'width' => null,
            'height' => null,
            'duration_seconds' => 32,
        ]))->analyse($asset);

        $asset->refresh();

        $this->assertSame(32, $asset->duration_seconds);
        $this->assertNull($asset->width, 'Audio has no picture, so there is no width to report.');
        $this->assertNull($asset->height);
        $this->assertNull($asset->aspect_ratio, 'A ratio needs both dimensions.');
        $this->assertSame(AssetMetadataStatus::Completed, $asset->metadata_status);
    }

    /*
    |--------------------------------------------------------------------------
    | Aspect ratio
    |--------------------------------------------------------------------------
    */

    /** @return array<string, array{int|null, int|null, float|null}> */
    public static function aspectRatioCases(): array
    {
        return [
            'landscape 1920x1080' => [1920, 1080, 1.7778],
            'portrait 1080x1920' => [1080, 1920, 0.5625],
            'square 1080x1080' => [1080, 1080, 1.0],
            'vertical short 720x1280' => [720, 1280, 0.5625],
            'no width' => [null, 1080, null],
            'no height' => [1920, null, null],
            'neither' => [null, null, null],
            'zero height cannot be divided by' => [1920, 0, null],
            'negative width' => [-1920, 1080, null],
        ];
    }

    #[Test]
    #[DataProvider('aspectRatioCases')]
    public function the_aspect_ratio_is_derived_from_measured_pixels(
        ?int $width,
        ?int $height,
        ?float $expected
    ): void {
        $service = $this->serviceWith();

        $this->assertSame($expected, $service->calculateAspectRatio($width, $height));
    }

    #[Test]
    public function the_aspect_ratio_comes_from_the_pixels_and_not_from_the_filename(): void
    {
        // The stored name says 16x9 and nothing about it is trustworthy.
        $asset = $this->attachedAsset(AssetType::Video, 'assets/9/16x9-landscape-final.mp4');

        $this->serviceWith($this->inspectorReturning(AssetType::Video, [
            'width' => 1080,
            'height' => 1920,
            'duration_seconds' => 9,
        ]))->analyse($asset);

        $this->assertSame(0.5625, (float) $asset->fresh()->aspect_ratio);
    }

    /*
    |--------------------------------------------------------------------------
    | Failure handling
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function a_failed_inspection_records_a_failure_and_persists_no_metadata(): void
    {
        $asset = $this->attachedAsset(AssetType::Video);

        // Pretend the previous inspection of an older file succeeded, so the
        // test can prove those values do not survive into the failure.
        $asset->forceFill([
            'width' => 1920,
            'height' => 1080,
            'duration_seconds' => 30,
            'aspect_ratio' => 1.7778,
            'metadata_status' => AssetMetadataStatus::Completed,
        ])->save();

        $this->serviceWith($this->inspectorReturning(
            AssetType::Video,
            ['width' => null, 'height' => null, 'duration_seconds' => null],
            new AssetMetadataException('The stored file could not be read as media.')
        ))->analyse($asset);

        $asset->refresh();

        $this->assertSame(AssetMetadataStatus::Failed, $asset->metadata_status);
        $this->assertSame('The stored file could not be read as media.', $asset->metadata_error);
        $this->assertNull($asset->width, 'A failed analysis must not keep the old file\'s width.');
        $this->assertNull($asset->height);
        $this->assertNull($asset->duration_seconds);
        $this->assertNull($asset->aspect_ratio);
    }

    #[Test]
    public function a_failed_analysis_does_not_throw(): void
    {
        $asset = $this->attachedAsset(AssetType::Video);

        $service = $this->serviceWith($this->inspectorReturning(
            AssetType::Video,
            ['width' => null, 'height' => null, 'duration_seconds' => null],
            new AssetMetadataException('unreadable')
        ));

        // The upload that triggered this must not be turned into an error.
        $this->assertSame(AssetMetadataStatus::Failed, $service->analyse($asset)->metadata_status);
    }

    #[Test]
    public function an_inspector_throwing_something_unexpected_is_still_only_a_failure(): void
    {
        $asset = $this->attachedAsset(AssetType::Video);

        $throwing = new class implements AssetMetadataInspector
        {
            public function handles(): AssetType
            {
                return AssetType::Video;
            }

            public function inspect(string $absolutePath, string $mimeType): array
            {
                throw new \LogicException('a bug, not a bad file');
            }
        };

        $this->serviceWith($throwing)->analyse($asset);

        $this->assertSame(AssetMetadataStatus::Failed, $asset->fresh()->metadata_status);
        $this->assertStringNotContainsString('LogicException', (string) $asset->fresh()->metadata_error);
    }

    #[Test]
    public function an_asset_without_a_file_is_marked_failed_rather_than_analysed(): void
    {
        $asset = Asset::factory()->create(['file_path' => null]);

        $this->serviceWith($this->inspectorReturning(AssetType::Video, [
            'width' => 1, 'height' => 1, 'duration_seconds' => 1,
        ]))->analyse($asset);

        $asset->refresh();

        $this->assertSame(AssetMetadataStatus::Failed, $asset->metadata_status);
        $this->assertStringContainsString('No file is attached', (string) $asset->metadata_error);
    }

    #[Test]
    public function a_row_pointing_at_a_file_that_is_no_longer_there_is_observable(): void
    {
        $path = 'assets/9/gone.mp4';
        $asset = $this->attachedAsset(AssetType::Video, $path);

        Storage::disk((string) config('assets.disk'))->delete($path);

        $this->serviceWith($this->inspectorReturning(AssetType::Video, [
            'width' => 1920, 'height' => 1080, 'duration_seconds' => 30,
        ]))->analyse($asset);

        $asset->refresh();

        $this->assertSame(AssetMetadataStatus::Failed, $asset->metadata_status);
        $this->assertNull($asset->width);
    }

    #[Test]
    public function an_asset_type_with_no_inspector_is_reported_rather_than_guessed_at(): void
    {
        $asset = $this->attachedAsset(AssetType::Other, 'assets/9/thing.bin', 'application/octet-stream');

        $this->serviceWith()->analyse($asset);

        $this->assertSame(AssetMetadataStatus::Failed, $asset->fresh()->metadata_status);
    }

    /*
    |--------------------------------------------------------------------------
    | Path safety
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function the_inspector_receives_a_path_on_disk_and_the_stored_mime_type(): void
    {
        $path = 'assets/9/clip.mp4';
        $asset = $this->attachedAsset(AssetType::Video, $path);

        $inspector = $this->inspectorReturning(AssetType::Video, [
            'width' => 320, 'height' => 240, 'duration_seconds' => 2,
        ], null, $seenPath);

        $this->serviceWith($inspector)->analyse($asset);

        $this->assertNotNull($seenPath);
        $this->assertStringEndsWith($path, str_replace('\\', '/', $seenPath));
        $this->assertSame('video/mp4', $inspector->seenMime);
    }

    #[Test]
    public function a_row_pointing_outside_the_asset_directory_is_never_inspected(): void
    {
        // Not written through the storage service, exactly as a hand edited
        // row would look.
        $asset = Asset::factory()->create([
            'file_path' => '../../../storage/logs/laravel.log',
            'file_name' => 'laravel.log',
            'mime_type' => 'text/plain',
        ]);

        $called = false;
        $spy = new class($called) implements AssetMetadataInspector
        {
            public function __construct(private bool &$called) {}

            public function handles(): AssetType
            {
                return AssetType::Video;
            }

            public function inspect(string $absolutePath, string $mimeType): array
            {
                $this->called = true;

                return ['width' => 1, 'height' => 1, 'duration_seconds' => 1];
            }
        };

        $this->serviceWith($spy)->analyse($asset);

        $this->assertFalse($called, 'A path outside the asset directory must not be inspected.');
        $this->assertSame(AssetMetadataStatus::Failed, $asset->fresh()->metadata_status);
    }

    /*
    |--------------------------------------------------------------------------
    | Preview eligibility
    |--------------------------------------------------------------------------
    */

    /** @return array<string, array{string|null, bool}> */
    /** @return array<string, array{string, bool}> */
    public static function directoryEscapeCases(): array
    {
        return [
            'plain relative path' => ['assets/7/clip.mp4', true],
            'directory itself' => ['assets', true],
            'climbing out with ..' => ['assets/../storage/logs/laravel.log', false],
            'climbing out from the root' => ['../assets/7/clip.mp4', false],
            'current directory segments' => ['assets/./7/clip.mp4', true],
            'outside the directory entirely' => ['storage/logs/laravel.log', false],
            'a lookalike prefix' => ['assets-backup/7/clip.mp4', false],
            'absolute posix path' => ['/etc/passwd', false],
            'absolute windows path' => ['C:\Windows\win.ini', false],
            'backslash traversal' => ['assets\..\storage\logs\laravel.log', false],
        ];
    }

    #[Test]
    #[DataProvider('directoryEscapeCases')]
    public function a_stored_path_is_only_trusted_inside_the_asset_directory(string $path, bool $expected): void
    {
        $this->assertSame($expected, $this->serviceWith()->isInsideAssetDirectory($path));
    }

    public static function previewableMimeCases(): array
    {
        return [
            'png' => ['image/png', true],
            'jpeg' => ['image/jpeg', true],
            'mp4' => ['video/mp4', true],
            'mp3' => ['audio/mpeg', true],
            'html is not media' => ['text/html', false],
            'javascript is not media' => ['application/javascript', false],
            'pdf is not media' => ['application/pdf', false],
            'octet stream is not media' => ['application/octet-stream', false],
            'no mime at all' => [null, false],
            // A type prefix with no subtype is not something to serve.
            'bare image prefix' => ['image', false],
        ];
    }

    #[Test]
    #[DataProvider('previewableMimeCases')]
    public function only_browsable_media_types_are_previewable(?string $mimeType, bool $expected): void
    {
        $this->assertSame($expected, $this->serviceWith()->isPreviewableMimeType($mimeType));
    }
}
