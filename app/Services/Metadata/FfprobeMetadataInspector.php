<?php

namespace App\Services\Metadata;

use App\Enums\AssetType;
use App\Exceptions\AssetMetadataException;
use RuntimeException;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessExceptionInterface;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Reads duration and, for video, dimensions from FFprobe.
 *
 * FFprobe is inspection only. It is never asked to write, transcode, remux, or
 * extract anything, and no argument it receives can make it: the command is
 * built as an argument array rather than a shell string, so the file path is
 * passed as exactly one argument and a filename containing a semicolon, a
 * backtick, or a newline stays a filename.
 *
 * The path handed in is always the absolute path this application resolved
 * from its own storage disk. There is no code path from a request to here, so
 * a client cannot choose what FFprobe opens.
 */
class FfprobeMetadataInspector implements AssetMetadataInspector
{
    public function __construct(
        private readonly string $binary,
        private readonly int $timeoutSeconds
    ) {}

    /**
     * Whether FFprobe can be executed at all in this environment.
     *
     * Asked once per binary per process rather than inferred from a failure.
     * Sniffing the operating system's own wording would be the shorter way:
     * an unresolvable executable reaches this code as an ordinary non-zero
     * exit code with a message like "'ffprobe' is not recognized as an
     * internal or external command", and that sentence changes with the
     * machine's locale and its shell. Running "-version" and looking at the
     * exit status says the same thing in every language.
     *
     * @var array<string, bool>
     */
    private static array $availability = [];

    public function isAvailable(): bool
    {
        if (! array_key_exists($this->binary, self::$availability)) {
            $probe = new Process([$this->binary, '-version']);
            $probe->setTimeout(10.0);

            try {
                $probe->run();
                self::$availability[$this->binary] = $probe->isSuccessful();
            } catch (Throwable) {
                self::$availability[$this->binary] = false;
            }
        }

        return self::$availability[$this->binary];
    }

    public function handles(): AssetType
    {
        return AssetType::Video;
    }

    /**
     * @return array{width: int|null, height: int|null, duration_seconds: int|null}
     *
     * @throws AssetMetadataException
     */
    public function inspect(string $absolutePath, string $mimeType): array
    {
        if (! $this->isAvailable()) {
            throw new AssetMetadataException(
                'Technical metadata cannot be read on this server because FFprobe is not available.',
                0,
                new RuntimeException('ffprobe binary '.$this->binary.' is not runnable')
            );
        }

        $probe = $this->run($absolutePath);

        $stream = $this->firstStreamOfType($probe, 'video');
        $duration = $this->firstUsableDuration($probe, $stream);

        $width = isset($stream['width']) ? (int) $stream['width'] : null;
        $height = isset($stream['height']) ? (int) $stream['height'] : null;

        if ($width !== null && $width < 1) {
            $width = null;
        }

        if ($height !== null && $height < 1) {
            $height = null;
        }

        return [
            'width' => $width,
            'height' => $height,
            'duration_seconds' => $duration,
        ];
    }

    /**
     * Decode the container header with FFprobe and return its JSON.
     *
     * -show_streams and -show_format together, because they answer different
     * halves of the question: the video stream carries the dimensions, while
     * the duration may sit on the stream or only on the container depending on
     * the format, and a file that reports it in just one of the two is not
     * broken.
     *
     * @return array{streams?: array<int, array<string, mixed>>, format?: array<string, mixed>}
     *
     * @throws AssetMetadataException
     */
    private function run(string $absolutePath): array
    {
        $process = new Process([
            $this->binary,
            '-v', 'error',
            '-print_format', 'json',
            '-show_streams',
            '-show_format',
            $absolutePath,
        ]);

        // A read timeout, not just a process timeout: a pathological file can
        // keep FFprobe alive without producing output, and a request must not
        // be held open by one.
        $process->setTimeout((float) $this->timeoutSeconds);

        try {
            $process->run();
        } catch (ProcessExceptionInterface|RuntimeException $e) {
            // Availability was confirmed before this point, so reaching here
            // means the process itself went wrong rather than the file being
            // unreadable: a timeout, or the system refusing to start it.
            throw new AssetMetadataException(
                'The stored file took too long to read and was not measured.',
                0,
                $e
            );
        }

        if (! $process->isSuccessful()) {
            throw new AssetMetadataException(
                'The stored file could not be read as media. It may be corrupted or incomplete.'
            );
        }

        $decoded = json_decode($process->getOutput(), true);

        if (! is_array($decoded)) {
            throw new AssetMetadataException(
                'The stored file could not be read as media. It may be corrupted or incomplete.'
            );
        }

        return $decoded;
    }

    /**
     * @param  array{streams?: array<int, array<string, mixed>>, format?: array<string, mixed>}  $probe
     * @return array<string, mixed>|null
     */
    private function firstStreamOfType(array $probe, string $codecType): ?array
    {
        foreach ($probe['streams'] ?? [] as $stream) {
            if (! is_array($stream)) {
                continue;
            }

            if (($stream['codec_type'] ?? null) === $codecType) {
                return $stream;
            }
        }

        return null;
    }

    /**
     * Duration in whole seconds, or null when the file does not report one.
     *
     * Rounded rather than truncated, because a 2.6 second clip is a three
     * second clip as far as an integer column is concerned, and 2 would make
     * it look shorter than it is.
     *
     * @param  array{streams?: array<int, array<string, mixed>>, format?: array<string, mixed>}  $probe
     * @param  array<string, mixed>|null  $stream
     */
    private function firstUsableDuration(array $probe, ?array $stream): ?int
    {
        $candidates = [
            $stream['duration'] ?? null,
            $probe['format']['duration'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '' || ! is_numeric($candidate)) {
                continue;
            }

            $seconds = (float) $candidate;

            if ($seconds > 0) {
                return (int) round($seconds);
            }
        }

        return null;
    }
}
