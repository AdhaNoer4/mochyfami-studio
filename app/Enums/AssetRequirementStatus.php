<?php

namespace App\Enums;

enum AssetRequirementStatus: string
{
    case Pending = 'pending';
    case Searching = 'searching';
    case Fulfilled = 'fulfilled';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Searching => 'Searching',
            self::Fulfilled => 'Fulfilled',
            self::Skipped => 'Skipped',
        };
    }

    /**
     * The single authoritative definition of reachable statuses.
     *
     * Fulfilled is deliberately terminal. Returning it to pending would imply
     * an asset was unassigned, and no asset unassignment workflow exists yet.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Searching, self::Skipped],
            self::Searching => [self::Fulfilled, self::Pending, self::Skipped],
            self::Fulfilled => [],
            self::Skipped => [self::Pending],
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
            self::Pending => 'Move to Pending',
            self::Searching => 'Start Searching',
            self::Fulfilled => 'Mark Fulfilled',
            self::Skipped => 'Skip Requirement',
            default => $target->label(),
        };
    }
}
