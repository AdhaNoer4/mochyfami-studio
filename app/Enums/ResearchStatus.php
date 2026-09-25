<?php

namespace App\Enums;

enum ResearchStatus: string
{
    case Pending = 'pending';
    case Researching = 'researching';
    case Completed = 'completed';
    case NeedsReview = 'needs_review';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Researching => 'Researching',
            self::Completed => 'Completed',
            self::NeedsReview => 'Needs Review',
            self::Failed => 'Failed',
        };
    }
}
