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

    /**
     * The single authoritative definition of which statuses are reachable
     * from this status. Reuse this from the backend and surface it through
     * the API so the frontend never needs to duplicate the transition map.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Researching],
            self::Researching => [self::Completed, self::NeedsReview, self::Failed],
            self::Completed => [self::NeedsReview],
            self::NeedsReview => [self::Completed, self::Researching, self::Failed],
            self::Failed => [self::Pending, self::Researching],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return false;
        }

        return in_array($target, $this->allowedTransitions(), true);
    }

    public function actionLabelFor(self $target): string
    {
        return match ($target) {
            self::Researching => 'Start Research',
            self::Completed => 'Mark Completed',
            self::NeedsReview => 'Mark Needs Review',
            self::Failed => 'Mark Failed',
            self::Pending => 'Reset to Pending',
            default => $target->label(),
        };
    }
}
