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
}
