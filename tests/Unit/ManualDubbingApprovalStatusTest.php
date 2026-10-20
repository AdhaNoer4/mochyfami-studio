<?php

namespace Tests\Unit;

use App\Enums\ManualDubbingApprovalStatus;
use Tests\TestCase;

class ManualDubbingApprovalStatusTest extends TestCase
{
    public function test_exposes_all_expected_cases(): void
    {
        $this->assertSame(
            ['draft', 'ready_for_review', 'approved', 'rejected'],
            array_column(ManualDubbingApprovalStatus::cases(), 'value')
        );
    }

    public function test_every_case_has_a_label(): void
    {
        foreach (ManualDubbingApprovalStatus::cases() as $case) {
            $this->assertNotSame('', $case->label());
        }
    }

    public function test_the_transition_graph_is_submit_review_and_two_terminal_outcomes(): void
    {
        $this->assertSame(
            [ManualDubbingApprovalStatus::ReadyForReview],
            ManualDubbingApprovalStatus::Draft->allowedTransitions()
        );
        $this->assertSame(
            [ManualDubbingApprovalStatus::Approved, ManualDubbingApprovalStatus::Rejected],
            ManualDubbingApprovalStatus::ReadyForReview->allowedTransitions()
        );
        $this->assertSame([], ManualDubbingApprovalStatus::Approved->allowedTransitions());
        $this->assertSame([], ManualDubbingApprovalStatus::Rejected->allowedTransitions());
    }

    public function test_only_graph_edges_are_allowed_and_self_loops_are_not(): void
    {
        $this->assertTrue(ManualDubbingApprovalStatus::Draft->canTransitionTo(ManualDubbingApprovalStatus::ReadyForReview));
        $this->assertTrue(ManualDubbingApprovalStatus::ReadyForReview->canTransitionTo(ManualDubbingApprovalStatus::Approved));
        $this->assertTrue(ManualDubbingApprovalStatus::ReadyForReview->canTransitionTo(ManualDubbingApprovalStatus::Rejected));

        $this->assertFalse(ManualDubbingApprovalStatus::Draft->canTransitionTo(ManualDubbingApprovalStatus::Approved));
        $this->assertFalse(ManualDubbingApprovalStatus::Draft->canTransitionTo(ManualDubbingApprovalStatus::Rejected));
        $this->assertFalse(ManualDubbingApprovalStatus::Approved->canTransitionTo(ManualDubbingApprovalStatus::ReadyForReview));
        $this->assertFalse(ManualDubbingApprovalStatus::Rejected->canTransitionTo(ManualDubbingApprovalStatus::ReadyForReview));
        $this->assertFalse(ManualDubbingApprovalStatus::Draft->canTransitionTo(ManualDubbingApprovalStatus::Draft));
    }
}
