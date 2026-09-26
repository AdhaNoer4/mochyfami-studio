<?php

namespace App\Enums;

enum ScriptStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Approved = 'approved';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Review => 'Review',
            self::Approved => 'Approved',
            self::Archived => 'Archived',
        };
    }

    /**
     * The single authoritative definition of which statuses are reachable
     * from this status. The frontend consumes this through the API and never
     * duplicates the transition map.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Review, self::Archived],
            self::Review => [self::Draft, self::Approved, self::Archived],
            self::Approved => [self::Archived],
            self::Archived => [self::Draft],
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
            self::Review => 'Send to Review',
            self::Draft => 'Back to Draft',
            self::Approved => 'Approve',
            self::Archived => 'Archive',
            default => $target->label(),
        };
    }
}
