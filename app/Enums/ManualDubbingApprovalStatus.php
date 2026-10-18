<?php

namespace App\Enums;

enum ManualDubbingApprovalStatus: string
{
    case Draft = 'draft';
    case ReadyForReview = 'ready_for_review';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::ReadyForReview => 'Ready for Review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }
}
