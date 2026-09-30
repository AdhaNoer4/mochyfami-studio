<?php

namespace Tests\Unit;

use App\Enums\AssetType;
use App\Exceptions\AssetFileStorageException;
use App\Exceptions\InvalidAssetFileTypeException;
use App\Models\Asset;
use App\Services\AssetFileStorageService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The MIME to extension map and the filename cleaner are pure functions of
 * configuration and input, so they are exercised here rather than through the
 * HTTP endpoint. What the endpoint does with the answers is covered by the
 * feature tests.
 */
class AssetFileTypeCompatibilityTest extends TestCase
{
    #[Test]
    public function test_every_media_type_accepts_at_least_one_format(): void
    {
        $service = new AssetFileStorageService;

        foreach ([AssetType::Video, AssetType::Image, AssetType::Audio] as $type) {
            $this->assertNotEmpty(
                $service->acceptedMimesFor($type),
                "{$type->value} should accept at least one file format."
            );
        }
    }

    #[Test]
    public function test_the_other_type_accepts_nothing(): void
    {
        $this->assertSame([], (new AssetFileStorageService)->acceptedMimesFor(AssetType::Other));
    }

    #[Test]
    #[DataProvider('compatibleMimeProvider')]
    public function test_a_matching_mime_type_is_accepted(AssetType $type, string $mimeType): void
    {
        $this->assertTrue($this->isCompatible($type, $mimeType));
    }

    /**
     * @return array<string, array{0: AssetType, 1: string}>
     */
    public static function compatibleMimeProvider(): array
    {
        return [
            'mp4 into video' => [AssetType::Video, 'video/mp4'],
            'quicktime into video' => [AssetType::Video, 'video/quicktime'],
            'webm into video' => [AssetType::Video, 'video/webm'],
            'jpeg into image' => [AssetType::Image, 'image/jpeg'],
            'png into image' => [AssetType::Image, 'image/png'],
            'mp3 into audio' => [AssetType::Audio, 'audio/mpeg'],
            'wav reported as x-wav into audio' => [AssetType::Audio, 'audio/x-wav'],
            'wav reported as wav into audio' => [AssetType::Audio, 'audio/wav'],
        ];
    }

    #[Test]
    #[DataProvider('incompatibleMimeProvider')]
    public function test_a_mismatched_mime_type_is_rejected(AssetType $type, string $mimeType): void
    {
        $this->assertFalse($this->isCompatible($type, $mimeType));
    }

    /**
     * @return array<string, array{0: AssetType, 1: string}>
     */
    public static function incompatibleMimeProvider(): array
    {
        return [
            'video into image' => [AssetType::Image, 'video/mp4'],
            'image into video' => [AssetType::Video, 'image/png'],
            'image into audio' => [AssetType::Audio, 'image/png'],
            'audio into video' => [AssetType::Video, 'audio/mpeg'],
            'a script into video' => [AssetType::Video, 'text/x-php'],
            'a pdf into image' => [AssetType::Image, 'application/pdf'],
            'a zip into image' => [AssetType::Image, 'application/zip'],
            'anything into other' => [AssetType::Other, 'video/mp4'],
            'an image into other' => [AssetType::Other, 'image/png'],
            'binary noise into other' => [AssetType::Other, 'application/octet-stream'],
        ];
    }

    #[Test]
    public function test_the_rejection_message_names_what_was_uploaded_and_what_is_accepted(): void
    {
        $this->expectException(InvalidAssetFileTypeException::class);
        $this->expectExceptionMessage('video/mp4');
        $this->expectExceptionMessage('Image');

        (new AssetFileStorageService)->assertCompatible(
            $this->assetOf(AssetType::Image),
            UploadedFile::fake()->create('clip.mp4', 4, 'video/mp4')
        );
    }

    #[Test]
    public function test_the_rejection_message_for_other_says_the_type_has_no_formats(): void
    {
        $this->expectException(InvalidAssetFileTypeException::class);
        $this->expectExceptionMessage('does not accept file uploads');

        (new AssetFileStorageService)->assertCompatible(
            $this->assetOf(AssetType::Other),
            UploadedFile::fake()->create('notes.pdf', 4, 'application/pdf')
        );
    }

    #[Test]
    public function test_storing_derives_the_extension_the_mime_map_claims_and_ignores_the_name(): void
    {
        $stored = $this->serviceStoringTo()
            ->store($this->assetOf(AssetType::Video), UploadedFile::fake()->create('holiday clip.mp4', 3, 'video/quicktime'));

        $this->assertStringEndsWith('.mov', $stored['path']);
        $this->assertStringStartsWith('assets/7/', $stored['path']);
    }

    #[Test]
    public function test_storing_records_the_detected_mime_the_real_size_and_the_original_name(): void
    {
        $stored = $this->serviceStoringTo()
            ->store($this->assetOf(AssetType::Video), UploadedFile::fake()->create('holiday clip.mp4', 3, 'video/mp4'));

        $this->assertSame('video/mp4', $stored['mime_type']);
        $this->assertSame(3072, $stored['file_size']);
        $this->assertSame('holiday clip.mp4', $stored['file_name']);
    }

    #[Test]
    public function test_a_wav_file_is_stored_with_one_extension_whatever_finfo_calls_it(): void
    {
        $service = $this->serviceStoringTo();
        $asset = $this->assetOf(AssetType::Audio);

        $asXWav = $service->store($asset, UploadedFile::fake()->create('clip.wav', 1, 'audio/x-wav'));
        $asWav = $service->store($asset, UploadedFile::fake()->create('clip.wav', 1, 'audio/wav'));

        $this->assertStringEndsWith('.wav', $asXWav['path']);
        $this->assertStringEndsWith('.wav', $asWav['path']);
    }

    #[Test]
    public function test_the_stored_filename_is_a_uuid_and_never_the_submitted_one(): void
    {
        $stored = $this->serviceStoringTo()
            ->store($this->assetOf(AssetType::Video), UploadedFile::fake()->create('../../evil.mp4', 1, 'video/mp4'));

        $this->assertStringNotContainsString('evil', $stored['path']);
        $this->assertStringNotContainsString('..', $stored['path']);
        $this->assertMatchesRegularExpression(
            '/^assets\/7\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.mp4$/',
            $stored['path']
        );
    }

    #[Test]
    public function test_a_failed_write_is_raised_rather_than_reported_as_success(): void
    {
        $service = $this->serviceStoringTo(returningFalse: true);

        $this->expectException(AssetFileStorageException::class);

        $service->store($this->assetOf(AssetType::Video), UploadedFile::fake()->create('clip.mp4', 1, 'video/mp4'));
    }

    #[Test]
    #[DataProvider('unsafeNameProvider')]
    public function test_an_untrusted_filename_is_reduced_to_a_safe_display_name(
        string $submitted,
        string $expected
    ): void {
        $this->assertSame(
            $expected,
            (new AssetFileStorageService)->normaliseOriginalName($submitted, 'fallback.mp4')
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function unsafeNameProvider(): array
    {
        return [
            'a plain name is kept' => ['cat walking.mp4', 'cat walking.mp4'],
            'posix traversal is stripped' => ['../../../.env', '.env'],
            'deep posix traversal is stripped' => ['../../../../etc/passwd', 'passwd'],
            'windows traversal is stripped' => ['..\\..\\..\\secret.mp4', 'secret.mp4'],
            'a windows absolute path is stripped' => ['C:\\Users\\me\\cat.mp4', 'cat.mp4'],
            'a unc path is stripped' => ['\\\\server\\share\\cat.mp4', 'cat.mp4'],
            'a mixed separator path is stripped' => ['/var/www/..\\cat.mp4', 'cat.mp4'],
            'a null byte is removed' => ["cat\0.mp4", 'cat.mp4'],
            'a control character is removed' => ["cat\r\n.mp4", 'cat.mp4'],
            'a bare dot is rejected' => ['.', 'fallback.mp4'],
            'a bare dot dot is rejected' => ['..', 'fallback.mp4'],
            'an empty name is rejected' => ['', 'fallback.mp4'],
            'a name of only separators is rejected' => ['///', 'fallback.mp4'],
            'a name of only control characters is rejected' => ["\0\0", 'fallback.mp4'],
        ];
    }

    #[Test]
    public function test_an_overlong_filename_is_truncated_to_the_column_width(): void
    {
        $name = (new AssetFileStorageService)->normaliseOriginalName(str_repeat('a', 400).'.mp4', 'fallback.mp4');

        $this->assertSame(255, mb_strlen($name));
    }

    #[Test]
    public function test_truncation_does_not_split_a_multi_byte_character(): void
    {
        $name = (new AssetFileStorageService)->normaliseOriginalName(str_repeat('é', 400), 'fallback.mp4');

        $this->assertSame(str_repeat('é', 255), $name);
    }

    #[Test]
    public function test_deleting_a_path_outside_the_asset_directory_is_refused(): void
    {
        $this->expectException(AssetFileStorageException::class);
        $this->expectExceptionMessage('outside the asset storage directory');

        $this->serviceStoringTo()->delete('../../.env');
    }

    #[Test]
    public function test_deleting_a_missing_path_is_a_no_op(): void
    {
        $service = $this->serviceStoringTo(fileExists: false);

        $service->delete('assets/7/never-stored.mp4');

        $this->assertTrue(true);
    }

    #[Test]
    public function test_deleting_a_null_path_is_a_no_op(): void
    {
        $this->serviceStoringTo()->delete(null);

        $this->assertTrue(true);
    }

    /**
     * A service whose disk is a double, so store() and delete() can be checked
     * without a filesystem. The double records what it was asked to do by
     * echoing the path back, exactly as a real local disk does.
     */
    private function serviceStoringTo(
        bool $returningFalse = false,
        bool $fileExists = true
    ): AssetFileStorageService {
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('putFileAs')
            ->andReturnUsing(fn (string $path, $file, ?string $name = null, array $options = []) => $returningFalse
                ? false
                : ltrim($path.'/'.$name, '/'));
        $disk->shouldReceive('exists')->andReturn($fileExists);
        $disk->shouldReceive('delete')->andReturn(true);

        $spy = new class($disk) extends AssetFileStorageService
        {
            public function __construct(private Filesystem $double) {}

            public function disk(): Filesystem
            {
                return $this->double;
            }
        };

        return $spy;
    }

    private function isCompatible(AssetType $type, string $mimeType): bool
    {
        try {
            (new AssetFileStorageService)->assertCompatible(
                $this->assetOf($type),
                UploadedFile::fake()->create('probe.bin', 1, $mimeType)
            );
        } catch (InvalidAssetFileTypeException) {
            return false;
        }

        return true;
    }

    private function assetOf(AssetType $type): Asset
    {
        $asset = new Asset;
        $asset->content_project_id = 7;
        $asset->type = $type;

        return $asset;
    }
}
