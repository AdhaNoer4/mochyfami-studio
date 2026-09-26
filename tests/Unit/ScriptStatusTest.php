<?php

namespace Tests\Unit;

use App\Enums\ScriptStatus;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ScriptStatusTest extends TestCase
{
    public function test_exposes_all_expected_cases(): void
    {
        $this->assertSame(
            ['draft', 'review', 'approved', 'archived'],
            array_column(ScriptStatus::cases(), 'value')
        );
    }

    public function test_draft_allows_review_and_archived(): void
    {
        $this->assertSame(
            [ScriptStatus::Review, ScriptStatus::Archived],
            ScriptStatus::Draft->allowedTransitions()
        );
    }

    public function test_review_allows_draft_approved_and_archived(): void
    {
        $this->assertSame(
            [ScriptStatus::Draft, ScriptStatus::Approved, ScriptStatus::Archived],
            ScriptStatus::Review->allowedTransitions()
        );
    }

    public function test_approved_allows_only_archived(): void
    {
        $this->assertSame([ScriptStatus::Archived], ScriptStatus::Approved->allowedTransitions());
    }

    public function test_archived_allows_only_draft(): void
    {
        $this->assertSame([ScriptStatus::Draft], ScriptStatus::Archived->allowedTransitions());
    }

    #[TestWith([ScriptStatus::Draft, ScriptStatus::Review])]
    #[TestWith([ScriptStatus::Draft, ScriptStatus::Archived])]
    #[TestWith([ScriptStatus::Review, ScriptStatus::Draft])]
    #[TestWith([ScriptStatus::Review, ScriptStatus::Approved])]
    #[TestWith([ScriptStatus::Review, ScriptStatus::Archived])]
    #[TestWith([ScriptStatus::Approved, ScriptStatus::Archived])]
    #[TestWith([ScriptStatus::Archived, ScriptStatus::Draft])]
    public function test_can_transition_to_for_valid_pairs(ScriptStatus $from, ScriptStatus $to): void
    {
        $this->assertTrue($from->canTransitionTo($to));
    }

    #[TestWith([ScriptStatus::Draft, ScriptStatus::Approved])]
    #[TestWith([ScriptStatus::Draft, ScriptStatus::Draft])]
    #[TestWith([ScriptStatus::Review, ScriptStatus::Review])]
    #[TestWith([ScriptStatus::Approved, ScriptStatus::Draft])]
    #[TestWith([ScriptStatus::Approved, ScriptStatus::Review])]
    #[TestWith([ScriptStatus::Archived, ScriptStatus::Archived])]
    #[TestWith([ScriptStatus::Archived, ScriptStatus::Approved])]
    public function test_can_transition_to_for_invalid_pairs(ScriptStatus $from, ScriptStatus $to): void
    {
        $this->assertFalse($from->canTransitionTo($to));
    }

    public function test_action_label_for_review_target(): void
    {
        $this->assertSame('Send to Review', ScriptStatus::Draft->actionLabelFor(ScriptStatus::Review));
    }

    public function test_action_label_for_draft_target(): void
    {
        $this->assertSame('Back to Draft', ScriptStatus::Review->actionLabelFor(ScriptStatus::Draft));
        $this->assertSame('Back to Draft', ScriptStatus::Archived->actionLabelFor(ScriptStatus::Draft));
    }

    public function test_action_label_for_approved_target(): void
    {
        $this->assertSame('Approve', ScriptStatus::Review->actionLabelFor(ScriptStatus::Approved));
    }

    public function test_action_label_for_archived_target(): void
    {
        $this->assertSame('Archive', ScriptStatus::Draft->actionLabelFor(ScriptStatus::Archived));
        $this->assertSame('Archive', ScriptStatus::Review->actionLabelFor(ScriptStatus::Archived));
        $this->assertSame('Archive', ScriptStatus::Approved->actionLabelFor(ScriptStatus::Archived));
    }
}
