<?php

namespace App\Services\Metadata;

use App\Enums\AssetType;
use App\Exceptions\AssetMetadataException;

/**
 * Reads duration from FFprobe for a file with no visual component.
 *
 * Identical in execution to the video inspector on purpose: one FFprobe
 * invocation, one failure handling, one place that knows how to talk to the
 * binary. The only thing that differs is what is asked for afterwards, which
 * is why this class is four lines long and delegates the reading rather than
 * duplicating it.
 *
 * It reports no width or height at all. Audio has no picture, and a number in
 * those columns would be invented data that a later comparison against a
 * visual requirement would happily believe.
 */
class AudioMetadataInspector implements AssetMetadataInspector
{
    public function __construct(
        private readonly FfprobeMetadataInspector $probe
    ) {}

    public function handles(): AssetType
    {
        return AssetType::Audio;
    }

    /**
     * @return array{width: null, height: null, duration_seconds: int|null}
     *
     * @throws AssetMetadataException
     */
    public function inspect(string $absolutePath, string $mimeType): array
    {
        $measured = $this->probe->inspect($absolutePath, $mimeType);

        return [
            'width' => null,
            'height' => null,
            'duration_seconds' => $measured['duration_seconds'],
        ];
    }
}
