<?php

namespace App\Services\Metadata;

use App\Enums\AssetType;
use App\Exceptions\AssetMetadataException;

/**
 * One media kind's reader, from a stored file to its technical facts.
 *
 * Split per kind rather than into one switch inside a service for two reasons.
 * The obvious one is that image, video, and audio genuinely need different
 * tools. The more useful one is testing: the expensive and environment
 * dependent part is running FFprobe, and behind this interface it can be
 * replaced by a double, so the tests that decide whether metadata is
 * persisted correctly do not depend on a binary being installed or on the
 * machine having FFmpeg.
 *
 * Implementations receive an absolute path that has already been resolved
 * through the configured disk and checked against the asset storage
 * directory. They never see a request, a stored path, or a filename.
 */
interface AssetMetadataInspector
{
    /**
     * Which asset type this inspector reads.
     *
     * The service dispatches on this rather than on an instanceof chain, so
     * adding a fourth kind is a new class plus a new enum case and not an edit
     * to a switch somebody has to remember to extend.
     */
    public function handles(): AssetType;

    /**
     * Read the file and return its technical facts.
     *
     * Values that do not apply to this media kind are returned as null rather
     * than invented. An audio file has no width, and reporting one would be a
     * fabrication that a later comparison would then trust.
     *
     * @return array{width: int|null, height: int|null, duration_seconds: int|null}
     *
     * @throws AssetMetadataException When the file cannot be read at all.
     */
    public function inspect(string $absolutePath, string $mimeType): array;
}
