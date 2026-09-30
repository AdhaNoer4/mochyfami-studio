<?php

namespace App\Enums;

enum AssetType: string
{
    case Video = 'video';
    case Image = 'image';
    case Audio = 'audio';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Video => 'Video',
            self::Image => 'Image',
            self::Audio => 'Audio',
            self::Other => 'Other',
        };
    }
}
