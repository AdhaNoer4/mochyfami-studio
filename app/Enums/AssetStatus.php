<?php

namespace App\Enums;

enum AssetStatus: string
{
    case Pending = 'pending';
    case Available = 'available';
    case Processing = 'processing';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Available => 'Available',
            self::Processing => 'Processing',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Archived => 'Archived',
        };
    }

    /**
     * The single authoritative definition of reachable statuses.
     *
     * The graph describes how an asset is expected to mature once a later
     * part actually stores and handles a file. Part 1 only creates pending
     * metadata, so it is documentation of the lifecycle rather than a
     * reachable state machine, and no transition endpoint exists yet.
     *
     * Archived is a holding state rather than a dead end: an archived asset
     * can be brought back to pending, because a metadata row may still be
     * worth re-trialling once its file exists.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Available, self::Archived],
            self::Available => [self::Processing, self::Approved, self::Rejected, self::Archived],
            self::Processing => [self::Available, self::Approved, self::Rejected],
            self::Approved => [self::Processing, self::Archived],
            self::Rejected => [self::Pending, self::Archived],
            self::Archived => [self::Pending],
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
