<?php

namespace App\Enums;

enum ContentIdeaStatus: string
{
    case Idea = 'idea';
    case Selected = 'selected';
    case Converted = 'converted';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Idea => 'Idea',
            self::Selected => 'Selected',
            self::Converted => 'Converted to Project',
            self::Archived => 'Archived',
        };
    }
}
