<?php

namespace App\Services;

use App\Enums\AssetMetadataStatus;
use App\Enums\AssetType;
use App\Exceptions\AssetMetadataException;
use App\Models\Asset;
use App\Services\Metadata\AssetMetadataInspector;
use App\Services\Metadata\AudioMetadataInspector;
use App\Services\Metadata\FfprobeMetadataInspector;
use App\Services\Metadata\ImageMetadataInspector;
use Throwable;

/**
 * Turns a stored file into the technical facts the asset domain can reason
 * about: dimensions, duration, and a derived aspect ratio.
 *
 * Two decisions are worth stating outright, because both were available and
 * both were declined.
 *
 * Analysis is synchronous. FFprobe reads a container header and returns in
 * milliseconds for the file sizes this studio handles, so a queue would trade
 * a known answer for an unknown one and leave a just-uploaded asset sitting in
 * a state nobody can interpret. Nothing here is slow enough to be worth
 * deferring.
 *
 * A failed inspection is recorded, not thrown. Losing the ability to measure a
 * file says nothing about the file itself, and a user who uploaded a valid
 * asset should not see that upload fail because a decoder could not read it.
 * The asset keeps its metadata, metadata_status becomes failed, and the reason
 * is stored in wording that is safe to display.
 */
class AssetMetadataService
{
    /**
     * Inspectors keyed by the asset type they read.
     *
     * Built from the container rather than hardcoded, so registering an
     * inspector is all it takes to support a new media kind. An asset whose
     * type has no inspector is not an error: "other" legitimately has none,
     * because it is the type that accepts no file at all.
     *
     * @var array<int, AssetMetadataInspector>
     */
    protected array $inspectors;

    /**
     * @param  array<int, AssetMetadataInspector>|null  $inspectors  Test doubles replace
     *                                                               the real inspectors
     *                                                               through this argument,
     *                                                               so the tests that
     *                                                               decide what is
     *                                                               persisted never
     *                                                               depend on FFmpeg
     *                                                               being installed.
     */
    public function __construct(
        protected AssetFileStorageService $files,
        ?array $inspectors = null
    ) {
        $this->inspectors = $inspectors ?? $this->defaultInspectors();
    }

    /**
     * Read a stored file and persist what was learned.
     *
     * The returned asset always reflects what was actually written. On failure
     * that means the previous metadata has been cleared and the status is
     * failed, never that the old values survived alongside a new error: stale
     * dimensions describing a file that is no longer there are worse than no
     * dimensions, because they look like an answer.
     */
    public function analyse(Asset $asset): Asset
    {
        $absolutePath = $this->absolutePathFor($asset);

        if ($absolutePath === null) {
            return $this->recordFailure($asset, 'No file is attached to this asset, so there is nothing to measure.');
        }

        $inspector = $this->inspectorFor($asset->type);

        if ($inspector === null) {
            return $this->recordFailure(
                $asset,
                "Technical metadata is not available for assets of type \"{$asset->type->label()}\"."
            );
        }

        // Marked before the read so a process that dies mid-inspection leaves
        // "pending" behind rather than the previous file's completed metadata.
        $asset->forceFill(['metadata_status' => AssetMetadataStatus::Pending])->save();

        try {
            $measured = $inspector->inspect($absolutePath, (string) $asset->mime_type);
        } catch (AssetMetadataException $e) {
            report($e);

            return $this->recordFailure($asset, $e->getMessage());
        } catch (Throwable $e) {
            // An inspector that throws something unexpected is still just a
            // failure to read the file as far as this asset is concerned. The
            // exception is logged with its stack; the user is told the file
            // could not be read.
            report($e);

            return $this->recordFailure($asset, 'The stored file could not be read.');
        }

        return $this->persist($asset, $measured);
    }

    /**
     * Width divided by height, rounded to the four decimals the column holds.
     *
     * Derived from the measured pixels and never from a filename or from the
     * asset's declared type: a 1080x1920 portrait video is 0.5625 whatever it
     * is called. Anything that is not two positive integers has no ratio, and
     * audio is the ordinary case of that.
     */
    public function calculateAspectRatio(?int $width, ?int $height): ?float
    {
        if ($width === null || $height === null || $width < 1 || $height < 1) {
            return null;
        }

        return round($width / $height, 4);
    }

    /**
     * Whether this asset's file may be handed to a browser as media.
     *
     * The preview endpoint and the client both need the same answer, and it
     * cannot be derived from the asset's type alone: a type says what is
     * allowed, this says what this particular stored file is. Anything that is
     * not a playable media type is refused, which is what keeps an HTML or
     * JavaScript upload from being served back to a browser as a document.
     */
    public function isPreviewableMimeType(?string $mimeType): bool
    {
        if ($mimeType === null) {
            return false;
        }

        // An allowlist of exact types, taken from the same configuration that
        // decides what may be uploaded, rather than a test on the "image",
        // "video" and "audio" prefixes. A prefix test looks equivalent and is
        // not: image/svg+xml passes it, and an SVG served inline from this
        // application's own origin is a stored cross-site scripting vector,
        // because a browser renders it as a document rather than a picture.
        return in_array($mimeType, $this->previewableMimeTypes(), true);
    }

    /**
     * Every MIME type the upload rules accept, flattened across asset types.
     *
     * Preview eligibility is derived from this rather than maintained beside
     * it. One list means a format added to uploads cannot be silently
     * unpreviewable, and one this application cannot upload cannot appear
     * here either.
     *
     * @return array<int, string>
     */
    private function previewableMimeTypes(): array
    {
        $perType = config('assets.upload.mimes', []);

        if (! is_array($perType) || $perType === []) {
            return [];
        }

        $types = [];

        foreach ($perType as $mimes) {
            if (is_array($mimes)) {
                // The MIME types are the keys of each map, not its values,
                // which are the extensions the files are stored under.
                $types = [...$types, ...array_keys($mimes)];
            }
        }

        return $types;
    }

    /**
     * Whether a stored path stays inside the configured asset directory.
     *
     * Resolution rather than a prefix test, for the reason above. It also
     * rejects an absolute path and any segment that climbs out, which a
     * prefix test happily accepts as long as the string still begins with the
     * directory name.
     */
    public function isInsideAssetDirectory(string $path): bool
    {
        $directory = rtrim(trim((string) config('assets.upload.directory'), '/'), '/');

        if ($directory === '' || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) === 1) {
            return false;
        }

        $segments = [];

        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                return false;
            }

            $segments[] = $segment;
        }

        $resolved = implode('/', $segments);

        return $resolved === $directory || str_starts_with($resolved, $directory.'/');
    }

    /**
     * Absolute path of the asset's stored file, or null when there is nothing
     * usable to read.
     *
     * Three things have to be true before any file is touched: the row claims a
     * file, that path is inside the asset storage directory, and the file is
     * actually there. The path is then resolved through the configured disk
     * rather than by concatenating a root, because the disk owns where its
     * files live and only it knows.
     *
     * The directory check cannot fire in normal operation, since only
     * AssetFileStorageService writes these paths. It is here so that a hand
     * edited row cannot turn analysis into a way of reading an arbitrary file
     * off the server.
     */
    private function absolutePathFor(Asset $asset): ?string
    {
        $path = $asset->file_path;

        if ($path === null || trim($path) === '') {
            return null;
        }

        // Compared after resolving the segments, not as a prefix on the raw
        // string. "assets/../storage/logs/laravel.log" starts with "assets/" and
        // is still a way out of the directory; normalising first is what makes
        // the check mean what it looks like it means.
        if (! $this->isInsideAssetDirectory($path)) {
            return null;
        }

        $disk = $this->files->disk();

        if (! $disk->exists($path)) {
            return null;
        }

        $absolute = $disk->path($path);

        // A disk that cannot produce a local path (S3, or a fake in a test that
        // only records writes) is not a disk that can be inspected here.
        return is_string($absolute) && $absolute !== '' ? $absolute : null;
    }

    private function inspectorFor(AssetType $type): ?AssetMetadataInspector
    {
        foreach ($this->inspectors as $inspector) {
            if ($inspector->handles() === $type) {
                return $inspector;
            }
        }

        return null;
    }

    /**
     * Write what was measured and mark the asset analysed.
     *
     * Every technical field is written on every run, including as null. A
     * partial write is what produces an image that still claims a duration
     * from a video it used to be.
     *
     * @param  array{width: int|null, height: int|null, duration_seconds: int|null}  $measured
     */
    private function persist(Asset $asset, array $measured): Asset
    {
        $asset->forceFill([
            'width' => $measured['width'],
            'height' => $measured['height'],
            'duration_seconds' => $measured['duration_seconds'],
            'aspect_ratio' => $this->calculateAspectRatio($measured['width'], $measured['height']),
            'metadata_status' => AssetMetadataStatus::Completed,
            'metadata_error' => null,
            'metadata_analyzed_at' => now(),
        ])->save();

        return $asset->fresh() ?? $asset;
    }

    /**
     * Record a failed analysis, discarding whatever the previous file's
     * inspection had left behind.
     */
    private function recordFailure(Asset $asset, string $message): Asset
    {
        $asset->forceFill([
            'width' => null,
            'height' => null,
            'duration_seconds' => null,
            'aspect_ratio' => null,
            'metadata_status' => AssetMetadataStatus::Failed,
            'metadata_error' => $message,
            'metadata_analyzed_at' => now(),
        ])->save();

        return $asset->fresh() ?? $asset;
    }

    /**
     * @return array<int, AssetMetadataInspector>
     */
    private function defaultInspectors(): array
    {
        $probe = new FfprobeMetadataInspector(
            (string) config('assets.metadata.ffprobe_binary', 'ffprobe'),
            (int) config('assets.metadata.timeout_seconds', 30)
        );

        return [
            new ImageMetadataInspector,
            $probe,
            new AudioMetadataInspector($probe),
        ];
    }
}
