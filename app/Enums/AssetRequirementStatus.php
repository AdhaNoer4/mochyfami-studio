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
     * Fulfilled is not terminal, and the one way back out is to searching. A
     * requirement is fulfilled on the strength of a selected asset, so when
     * that asset stops being usable the requirement has to stop claiming to be
     * fulfilled. Deleting the selected asset is the concrete case: the foreign
     * key nulls out, and the row is left to say so rather than sitting on a
     * fulfillment backed by nothing.
     *
     * The way back is deliberately not to pending. A requirement that was
     * being worked on has already been picked up, and losing its asset does not
     * un-pick it. Skipped is also unreachable from here for the same reason it
     * is reachable from searching: skipping is a decision about the
     * requirement, not about the asset that happened to fail.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Searching, self::Skipped],
            self::Searching => [self::Fulfilled, self::Pending, self::Skipped],
            self::Fulfilled => [self::Searching],
            self::Skipped => [self::Pending],
        };
    }

    /**
     * The action label differs by direction, because the same target status
     * means different things depending on where it is coming from.
     *
     * Moving into searching is the user opening the requirement up to work on
     * it; moving out of fulfilled is something that happened to the
     * requirement, not a choice the user made at a button.
     */
    public function actionLabelFor(self $target): string
    {
        if ($this === self::Fulfilled && $target === self::Searching) {
            return 'Reopen for Searching';
        }

        return match ($target) {
            self::Pending => 'Move to Pending',
            self::Searching => 'Start Searching',
            self::Fulfilled => 'Mark Fulfilled',
            self::Skipped => 'Skip Requirement',
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
