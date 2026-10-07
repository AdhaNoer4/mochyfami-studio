<?php

namespace Tests\Unit;

use App\Enums\TtsTrackStatus;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TtsTrackStatusTest extends TestCase
{
    public function test_exposes_all_expected_cases(): void
    {
        $this->assertSame(
            ['pending', 'generating', 'completed', 'failed', 'archived'],
            array_column(TtsTrackStatus::cases(), 'value')
        );
    }

    public function test_every_case_has_a_label(): void
    {
        foreach (TtsTrackStatus::cases() as $case) {
            $this->assertNotSame('', $case->label());
        }
    }

    public function test_pending_allows_generation_or_archiving(): void
    {
        $this->assertSame(
            [TtsTrackStatus::Generating, TtsTrackStatus::Archived],
            TtsTrackStatus::Pending->allowedTransitions()
        );
    }

    public function test_generating_allows_completion_or_failure(): void
    {
        $this->assertSame(
            [TtsTrackStatus::Completed, TtsTrackStatus::Failed],
            TtsTrackStatus::Generating->allowedTransitions()
        );
    }

    public function test_completed_allows_only_archiving(): void
    {
        $this->assertSame(
            [TtsTrackStatus::Archived],
            TtsTrackStatus::Completed->allowedTransitions()
        );
    }

    public function test_failed_allows_only_archiving(): void
    {
        $this->assertSame(
            [TtsTrackStatus::Archived],
            TtsTrackStatus::Failed->allowedTransitions()
        );
    }

    public function test_archived_is_a_terminal_state(): void
    {
        $this->assertSame([], TtsTrackStatus::Archived->allowedTransitions());
    }

    #[TestWith([TtsTrackStatus::Pending, TtsTrackStatus::Generating])]
    #[TestWith([TtsTrackStatus::Pending, TtsTrackStatus::Archived])]
    #[TestWith([TtsTrackStatus::Generating, TtsTrackStatus::Completed])]
    #[TestWith([TtsTrackStatus::Generating, TtsTrackStatus::Failed])]
    #[TestWith([TtsTrackStatus::Completed, TtsTrackStatus::Archived])]
    #[TestWith([TtsTrackStatus::Failed, TtsTrackStatus::Archived])]
    public function test_can_transition_to_for_valid_pairs(TtsTrackStatus $from, TtsTrackStatus $to): void
    {
        $this->assertTrue($from->canTransitionTo($to));
    }

    #[TestWith([TtsTrackStatus::Pending, TtsTrackStatus::Pending])]
    #[TestWith([TtsTrackStatus::Generating, TtsTrackStatus::Generating])]
    #[TestWith([TtsTrackStatus::Completed, TtsTrackStatus::Completed])]
    #[TestWith([TtsTrackStatus::Failed, TtsTrackStatus::Failed])]
    #[TestWith([TtsTrackStatus::Archived, TtsTrackStatus::Archived])]
    public function test_a_status_never_transitions_to_itself(TtsTrackStatus $status): void
    {
        $this->assertFalse($status->canTransitionTo($status));
    }

    #[TestWith([TtsTrackStatus::Pending, TtsTrackStatus::Completed])]
    #[TestWith([TtsTrackStatus::Pending, TtsTrackStatus::Failed])]
    #[TestWith([TtsTrackStatus::Generating, TtsTrackStatus::Archived])]
    #[TestWith([TtsTrackStatus::Generating, TtsTrackStatus::Pending])]
    #[TestWith([TtsTrackStatus::Completed, TtsTrackStatus::Generating])]
    #[TestWith([TtsTrackStatus::Completed, TtsTrackStatus::Failed])]
    #[TestWith([TtsTrackStatus::Completed, TtsTrackStatus::Pending])]
    #[TestWith([TtsTrackStatus::Failed, TtsTrackStatus::Generating])]
    #[TestWith([TtsTrackStatus::Failed, TtsTrackStatus::Completed])]
    #[TestWith([TtsTrackStatus::Failed, TtsTrackStatus::Pending])]
    #[TestWith([TtsTrackStatus::Archived, TtsTrackStatus::Pending])]
    #[TestWith([TtsTrackStatus::Archived, TtsTrackStatus::Generating])]
    #[TestWith([TtsTrackStatus::Archived, TtsTrackStatus::Completed])]
    #[TestWith([TtsTrackStatus::Archived, TtsTrackStatus::Failed])]
    public function test_can_transition_to_for_rejected_pairs(TtsTrackStatus $from, TtsTrackStatus $to): void
    {
        $this->assertFalse($from->canTransitionTo($to));
    }
}
