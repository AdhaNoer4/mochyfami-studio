<?php

namespace App\Services\Metadata;

use App\Enums\AssetType;
use App\Exceptions\AssetMetadataException;

/**
 * Reads width and height from a stored image using PHP's own getimagesize().
 *
 * No image library is added for this. GD is already loaded, but getimagesize()
 * reads only the header rather than decoding the whole file, which is both
 * faster and safer: a 500 MB image cannot make this process allocate 500 MB to
 * answer a question about two integers, and a decompression bomb is never
 * decoded at all.
 */
class ImageMetadataInspector implements AssetMetadataInspector
{
    public function handles(): AssetType
    {
        return AssetType::Image;
    }

    /**
     * @return array{width: int, height: int, duration_seconds: null}
     *
     * @throws AssetMetadataException
     */
    public function inspect(string $absolutePath, string $mimeType): array
    {
        // Suppressed because the warning it stops is the error we handle
        // ourselves below, with wording that is safe to show a user.
        $info = @getimagesize($absolutePath);

        if ($info === false) {
            throw new AssetMetadataException(
                'The stored image could not be read. It may be corrupted or incomplete.'
            );
        }

        $width = isset($info[0]) ? (int) $info[0] : 0;
        $height = isset($info[1]) ? (int) $info[1] : 0;

        // A zero dimension is reported by getimagesize for a few exotic
        // formats. Storing it would produce an aspect ratio of 0 or a divide
        // by zero downstream, so it is treated as unreadable rather than as a
        // real measurement.
        if ($width < 1 || $height < 1) {
            throw new AssetMetadataException(
                'The stored image reports no usable dimensions and cannot be measured.'
            );
        }

        return [
            'width' => $width,
            'height' => $height,
            // An image has no duration. Returning null is the honest answer;
            // zero would look like an instantaneous asset.
            'duration_seconds' => null,
        ];
    }
}
