<?php

namespace App\Enums;

enum ContentProjectStatus: string
{
    case Draft = 'draft';
    case Researching = 'researching';
    case ResearchReview = 'research_review';
    case Scripting = 'scripting';
    case ScriptReview = 'script_review';
    case AssetCollection = 'asset_collection';
    case Production = 'production';
    case VideoReview = 'video_review';
    case Revision = 'revision';
    case Approved = 'approved';
    case Published = 'published';
    case Archived = 'archived';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Researching => 'Researching',
            self::ResearchReview => 'Research Review',
            self::Scripting => 'Scripting',
            self::ScriptReview => 'Script Review',
            self::AssetCollection => 'Asset Collection',
            self::Production => 'Production / Rendering',
            self::VideoReview => 'Video Review',
            self::Revision => 'Needs Revision',
            self::Approved => 'Approved',
            self::Published => 'Published',
            self::Archived => 'Archived',
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
            self::Draft => [self::Researching, self::Archived, self::Failed],
            self::Researching => [self::ResearchReview, self::Revision, self::Failed, self::Archived],
            self::ResearchReview => [self::Scripting, self::Revision, self::Failed, self::Archived],
            self::Scripting => [self::ScriptReview, self::Revision, self::Failed, self::Archived],
            self::ScriptReview => [self::AssetCollection, self::Revision, self::Failed, self::Archived],
            self::AssetCollection => [self::Production, self::Revision, self::Failed, self::Archived],
            self::Production => [self::VideoReview, self::Revision, self::Failed, self::Archived],
            self::VideoReview => [self::Approved, self::Revision, self::Failed, self::Archived],
            self::Approved => [self::Published, self::Revision, self::Archived],
            self::Published => [self::Archived],
            self::Revision => [self::Researching, self::Scripting, self::Production, self::Failed, self::Archived],
            self::Archived, self::Failed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return false;
        }

        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Human-readable label for the action that performs a transition to $target.
     */
    public function actionLabelFor(self $target): string
    {
        if ($this === self::Revision) {
            return match ($target) {
                self::Researching => 'Return to Research',
                self::Scripting => 'Return to Scripting',
                self::Production => 'Return to Production',
                default => $target->label(),
            };
        }

        return match ($target) {
            self::Researching => 'Start Research',
            self::ResearchReview => 'Submit Research for Review',
            self::Scripting => 'Start Scripting',
            self::ScriptReview => 'Submit Script for Review',
            self::AssetCollection => 'Start Asset Collection',
            self::Production => 'Start Production',
            self::VideoReview => 'Submit Video for Review',
            self::Approved => 'Approve Video',
            self::Published => 'Mark as Published',
            self::Revision => 'Send to Revision',
            self::Archived => 'Archive Project',
            self::Failed => 'Mark Failed',
            default => $target->label(),
        };
    }
}
