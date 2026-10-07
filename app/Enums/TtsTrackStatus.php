<?php

namespace App\Enums;

enum TtsTrackStatus: string
{
    case Pending = 'pending';
    case Generating = 'generating';
    case Completed = 'completed';
    case Failed = 'failed';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Generating => 'Generating',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Archived => 'Archived',
        };
    }

    /**
     * The single authoritative definition of reachable statuses.
     *
     * Tracks are written by the generation/regeneration service and the delete
     * handler, never through a public status endpoint. archived is a terminal
     * state: deleting a track keeps its audio asset anyway, so there is nothing
     * for a track to be revived for.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Generating, self::Archived],
            self::Generating => [self::Completed, self::Failed],
            self::Completed => [self::Archived],
            self::Failed => [self::Archived],
            self::Archived => [],
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
