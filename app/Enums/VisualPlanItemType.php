<?php

namespace App\Enums;

enum VisualPlanItemType: string
{
    case AnimalClip = 'animal_clip';
    case StockVideo = 'stock_video';
    case Photo = 'photo';
    case ScreenRecording = 'screen_recording';
    case Graphic = 'graphic';
    case Text = 'text';
    case BRoll = 'b_roll';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::AnimalClip => 'Animal Clip',
            self::StockVideo => 'Stock Video',
            self::Photo => 'Photo',
            self::ScreenRecording => 'Screen Recording',
            self::Graphic => 'Graphic',
            self::Text => 'Text',
            self::BRoll => 'B-Roll',
            self::Other => 'Other',
        };
    }
}
