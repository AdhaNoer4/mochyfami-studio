<?php

namespace App\Enums;

enum VisualPlanSection: string
{
    case Hook = 'hook';
    case Body = 'body';
    case Closing = 'closing';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Hook => 'Hook',
            self::Body => 'Body',
            self::Closing => 'Closing',
            self::Other => 'Other',
        };
    }
}
