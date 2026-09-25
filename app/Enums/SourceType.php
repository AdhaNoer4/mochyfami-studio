<?php

namespace App\Enums;

enum SourceType: string
{
    case Article = 'article';
    case Academic = 'academic';
    case Official = 'official';
    case News = 'news';
    case Documentation = 'documentation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Article => 'Article',
            self::Academic => 'Academic',
            self::Official => 'Official',
            self::News => 'News',
            self::Documentation => 'Documentation',
            self::Other => 'Other',
        };
    }
}
