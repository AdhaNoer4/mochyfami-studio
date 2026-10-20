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

    /**
     * The single authoritative definition of reachable approval statuses.
     *
     * The graph is deliberately minimal: a draft is submitted once, the
     * reviewer either approves or rejects it, and both outcomes are terminal.
     * A rejected recording is revised by uploading a new one, which starts a
     * fresh track, so no path leads back out of rejected. Approval metadata
     * (approved_at, approved_by) is therefore only ever written on the one
     * transition into approved and can never contradict a track's status.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::ReadyForReview],
            self::ReadyForReview => [self::Approved, self::Rejected],
            self::Approved => [],
            self::Rejected => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return false;
        }

        return in_array($target, $this->allowedTransitions(), true);
    }
}
