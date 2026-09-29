<?php

namespace App\Enums;

enum AssetRequirementType: string
{
    case Video = 'video';
    case Image = 'image';
    case Audio = 'audio';
    case Graphic = 'graphic';
    case ScreenRecording = 'screen_recording';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Video => 'Video',
            self::Image => 'Image',
            self::Audio => 'Audio',
            self::Graphic => 'Graphic',
            self::ScreenRecording => 'Screen Recording',
            self::Other => 'Other',
        };
    }

    /**
     * The deterministic mapping from a visual plan item type to the kind of
     * asset it requires.
     *
     * This is a fixed editorial rule, not a judgement call. A visual plan item
     * describes one planned shot, and the requirement it generates asks for
     * footage of that kind.
     */
    public static function fromVisualType(VisualPlanItemType $visualType): self
    {
        return match ($visualType) {
            VisualPlanItemType::AnimalClip,
            VisualPlanItemType::StockVideo,
            VisualPlanItemType::BRoll,
            VisualPlanItemType::ScreenRecording => self::Video,
            VisualPlanItemType::Photo => self::Image,
            VisualPlanItemType::Graphic,
            VisualPlanItemType::Text => self::Graphic,
            VisualPlanItemType::Other => self::Other,
        };
    }
}
