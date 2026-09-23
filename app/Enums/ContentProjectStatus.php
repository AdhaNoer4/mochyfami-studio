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

    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return true;
        }

        if ($target === self::Failed || $target === self::Archived) {
            return true;
        }

        return match ($this) {
            self::Draft => in_array($target, [self::Researching, self::Scripting]),
            self::Researching => in_array($target, [self::ResearchReview, self::Scripting]),
            self::ResearchReview => in_array($target, [self::Scripting, self::Researching]),
            self::Scripting => in_array($target, [self::ScriptReview, self::AssetCollection]),
            self::ScriptReview => in_array($target, [self::AssetCollection, self::Scripting]),
            self::AssetCollection => in_array($target, [self::Production, self::Scripting]),
            self::Production => in_array($target, [self::VideoReview]),
            self::VideoReview => in_array($target, [self::Approved, self::Revision]),
            self::Revision => in_array($target, [self::Production, self::Scripting, self::AssetCollection]),
            self::Approved => in_array($target, [self::Published]),
            self::Published, self::Archived, self::Failed => false,
        };
    }
}
