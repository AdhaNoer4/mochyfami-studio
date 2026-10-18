<?php

namespace App\Enums;

enum TtsTrackSource: string
{
    case Tts = 'tts';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Tts => 'TTS',
            self::Manual => 'Manual',
        };
    }
}
