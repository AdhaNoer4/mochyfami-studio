<?php

namespace App\Enums;

enum AssetRequirementAspectRatio: string
{
    case Portrait916 = '9:16';
    case Landscape169 = '16:9';
    case Square11 = '1:1';
    case Portrait45 = '4:5';

    /**
     * The studio produces vertical short-form content, so portrait is the
     * default target for generated requirements.
     */
    public const DEFAULT = self::Portrait916;

    public function label(): string
    {
        return $this->value;
    }

    /**
     * The measured ratio this named ratio means.
     *
     * A requirement stores a name like 9:16 and an asset stores the number
     * inspection measured, so the two can only be compared through this value.
     * The numbers are the real divisions rather than the stored ones: 16:9 is
     * 1.777..., not the 1.7778 that decimal:4 rounds it to, and comparing
     * against the rounded figure would let rounding decide whether footage
     * matches.
     */
    public function canonicalRatio(): float
    {
        return match ($this) {
            self::Portrait916 => 9 / 16,
            self::Landscape169 => 16 / 9,
            self::Square11 => 1.0,
            self::Portrait45 => 4 / 5,
        };
    }

    /**
     * Whether a measured ratio counts as this one.
     *
     * The tolerance is what makes the comparison honest. Real footage is
     * rarely exactly 16:9: a phone crops a hair, an editor reframes, an export
     * rounds. An exact float comparison would reject a genuinely correct
     * asset over the fourth decimal place, so ratios within a hundredth of
     * each other are the same ratio for this purpose.
     *
     * A null measurement never matches. Missing is not zero and is not a
     * portrait shape; it is the absence of an answer, and treating it as a
     * pass would let an uninspected asset satisfy a shape requirement.
     */
    public function matches(?float $measured, float $tolerance = 0.01): bool
    {
        if ($measured === null) {
            return false;
        }

        return abs($this->canonicalRatio() - $measured) <= $tolerance;
    }
}
