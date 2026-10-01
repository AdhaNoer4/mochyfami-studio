<?php

namespace Tests\Unit;

use App\Exceptions\AssetMetadataException;
use App\Services\Metadata\AudioMetadataInspector;
use App\Services\Metadata\FfprobeMetadataInspector;
use App\Services\Metadata\ImageMetadataInspector;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * The readers, exercised against real files.
 *
 * Unlike the pipeline tests, these do touch the outside world: one builds an
 * image with GD and the other runs FFprobe. That is the point of putting them
 * in separate files. What the pipeline persists is tested without any external
 * tool, and what the tools actually report is tested once, here.
 *
 * FFprobe is skipped rather than faked when it is not installed, because a
 * machine without it cannot answer the question these tests ask. The
 * video-specific coverage lives in the pipeline tests, which do not need it.
 */
class AssetMetadataInspectorTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Images
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function an_image_is_measured_from_its_own_header(): void
    {
        $path = $this->writePng(320, 240);

        $measured = (new ImageMetadataInspector)->inspect($path, 'image/png');

        $this->assertSame(320, $measured['width']);
        $this->assertSame(240, $measured['height']);
        $this->assertNull($measured['duration_seconds'], 'An image has no duration.');
    }

    #[Test]
    public function a_file_that_is_not_an_image_is_refused_rather_than_guessed_at(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'notanimage');
        file_put_contents($path, str_repeat('this is not a PNG at all', 40));

        try {
            $this->expectException(AssetMetadataException::class);

            (new ImageMetadataInspector)->inspect($path, 'image/png');
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function the_image_error_never_names_the_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'notanimage');
        file_put_contents($path, 'nonsense');

        try {
            (new ImageMetadataInspector)->inspect($path, 'image/png');
            $this->fail('An unreadable file should have been refused.');
        } catch (AssetMetadataException $e) {
            $this->assertStringNotContainsString($path, $e->getMessage());
            $this->assertStringNotContainsString(sys_get_temp_dir(), $e->getMessage());
        } finally {
            @unlink($path);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FFprobe
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function ffprobe_reads_a_duration_from_a_real_audio_file(): void
    {
        $probe = $this->probe();

        // A three second WAV written by hand. Its header is a fixed size, so
        // the duration needs no codec and no encoder to be readable.
        $measured = (new AudioMetadataInspector($probe))->inspect($this->writeWav(3), 'audio/wav');

        $this->assertSame(3, $measured['duration_seconds']);
        $this->assertNull($measured['width']);
        $this->assertNull($measured['height']);
    }

    #[Test]
    public function ffprobe_reports_audio_without_visual_dimensions(): void
    {
        $measured = (new AudioMetadataInspector($this->probe()))->inspect($this->writeWav(7), 'audio/wav');

        $this->assertNull($measured['width'], 'A WAV has no picture, so there is nothing to measure.');
        $this->assertNull($measured['height']);
    }

    #[Test]
    public function ffprobe_refuses_a_file_that_is_not_media(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'notmedia');
        file_put_contents($path, 'plain text pretending to be an mp4');

        try {
            $this->expectException(AssetMetadataException::class);

            (new FfprobeMetadataInspector($this->binary(), 30))->inspect($path, 'video/mp4');
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function a_missing_ffprobe_is_reported_as_an_environment_problem(): void
    {
        $path = $this->writeWav(1);

        try {
            (new FfprobeMetadataInspector('definitely-not-a-real-binary-xyz', 5))
                ->inspect($path, 'audio/wav');

            $this->fail('A missing binary should not have produced a measurement.');
        } catch (AssetMetadataException $e) {
            $this->assertStringContainsString('FFprobe', $e->getMessage());
            // The wording is safe to show a user: it names the tool and not
            // the path it was pointed at.
            $this->assertStringNotContainsString($path, $e->getMessage());
        }
    }

    #[Test]
    public function the_path_reaches_ffprobe_as_one_argument_and_never_through_a_shell(): void
    {
        // A filename built out of shell metacharacters. Passed as an argument
        // array it stays one path; through a shell it would be a command.
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ffprobe test; touch pwned.txt';
        @mkdir($directory, 0777, true);
        $path = $directory.DIRECTORY_SEPARATOR.'clip.mp4';
        file_put_contents($path, 'not really a video');

        try {
            try {
                (new FfprobeMetadataInspector($this->binary(), 30))->inspect($path, 'video/mp4');
            } catch (AssetMetadataException) {
                // The point is that it failed as a file read, not as a shell.
            }

            $this->assertFileDoesNotExist(
                $directory.DIRECTORY_SEPARATOR.'pwned.txt',
                'A filename must never be interpreted as a command.'
            );
        } finally {
            @unlink($path);
            @rmdir($directory);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function binary(): string
    {
        return (string) config('assets.metadata.ffprobe_binary', 'ffprobe');
    }

    private function probe(): FfprobeMetadataInspector
    {
        $binary = $this->binary();

        if (! $this->canRun($binary)) {
            $this->markTestSkipped('FFprobe is not available in this environment.');
        }

        return new FfprobeMetadataInspector($binary, (int) config('assets.metadata.timeout_seconds', 30));
    }

    private function canRun(string $binary): bool
    {
        $process = new Process([$binary, '-version']);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * A real PNG of the requested size, written with GD.
     */
    private function writePng(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);

        $path = tempnam(sys_get_temp_dir(), 'img').'.png';

        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    /**
     * A silent mono PCM WAV of the requested length.
     *
     * Written byte by byte rather than shipped as a fixture so the test has no
     * binary blob in the repository, and so the expected duration is a number
     * in the test rather than something a file has to agree with.
     */
    private function writeWav(int $seconds): string
    {
        $sampleRate = 8000;
        $bitsPerSample = 8;
        $channels = 1;
        $blockAlign = intdiv($channels * $bitsPerSample, 8);
        $dataSize = $sampleRate * $blockAlign * $seconds;
        $byteRate = $sampleRate * $blockAlign;

        $samples = str_repeat("\x80", $dataSize);

        $header = 'RIFF'
            .pack('V', 36 + $dataSize)
            .'WAVE'
            .'fmt '
            .pack('V', 16)
            .pack('v', 1)
            .pack('v', $channels)
            .pack('V', $sampleRate)
            .pack('V', $byteRate)
            .pack('v', $blockAlign)
            .pack('v', $bitsPerSample)
            .'data'
            .pack('V', $dataSize);

        $path = tempnam(sys_get_temp_dir(), 'audio').'.wav';
        file_put_contents($path, $header.$samples);

        return $path;
    }
}
