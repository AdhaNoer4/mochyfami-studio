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
     * Whether an asset of this type could satisfy a requirement of this type.
     *
     * The two vocabularies are not the same size. A requirement distinguishes
     * a graphic from a photo and a screen recording from other footage,
     * because those are different editorial asks; an asset only records what
     * the file actually is. So a graphic is satisfied by any image and a screen
     * recording by any video, rather than by a narrower thing an asset cannot
     * express.
     *
     * Other maps to nothing. An asset of type Other has no measurable type to
     * check its metadata against, so it cannot be verified as a fit for
     * anything, and letting it through would make an unmeasurable asset look
     * like a confirmed match.
     */
    public function accepts(AssetType $type): bool
    {
        return match ($this) {
            self::Video, self::ScreenRecording => $type === AssetType::Video,
            self::Image, self::Graphic => $type === AssetType::Image,
            self::Audio => $type === AssetType::Audio,
            self::Other => false,
        };
    }

    /**
     * Whether this requirement is measured in time.
     *
     * Duration only applies to media that plays. An image or a graphic has no
     * runtime at all, so a target duration against one is not a duration check
     * to run, it is a requirement the asset cannot answer either way.
     */
    public function isTimeBound(): bool
    {
        return match ($this) {
            self::Video, self::ScreenRecording, self::Audio => true,
            self::Image, self::Graphic, self::Other => false,
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
