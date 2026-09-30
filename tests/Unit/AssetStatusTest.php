<?php

namespace Tests\Unit;

use App\Enums\AssetStatus;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AssetStatusTest extends TestCase
{
    public function test_exposes_all_expected_cases(): void
    {
        $this->assertSame(
            ['pending', 'available', 'processing', 'approved', 'rejected', 'archived'],
            array_column(AssetStatus::cases(), 'value')
        );
    }

    public function test_every_case_has_a_label(): void
    {
        foreach (AssetStatus::cases() as $case) {
            $this->assertNotSame('', $case->label());
        }
    }

    public function test_pending_allows_available_and_archived(): void
    {
        $this->assertSame(
            [AssetStatus::Available, AssetStatus::Archived],
            AssetStatus::Pending->allowedTransitions()
        );
    }

    public function test_available_allows_processing_approval_rejection_and_archiving(): void
    {
        $this->assertSame(
            [AssetStatus::Processing, AssetStatus::Approved, AssetStatus::Rejected, AssetStatus::Archived],
            AssetStatus::Available->allowedTransitions()
        );
    }

    public function test_processing_allows_back_to_available_then_approval_or_rejection(): void
    {
        $this->assertSame(
            [AssetStatus::Available, AssetStatus::Approved, AssetStatus::Rejected],
            AssetStatus::Processing->allowedTransitions()
        );
    }

    public function test_approved_allows_reprocessing_and_archiving(): void
    {
        $this->assertSame(
            [AssetStatus::Processing, AssetStatus::Archived],
            AssetStatus::Approved->allowedTransitions()
        );
    }

    public function test_rejected_allows_a_retry_or_archiving(): void
    {
        $this->assertSame(
            [AssetStatus::Pending, AssetStatus::Archived],
            AssetStatus::Rejected->allowedTransitions()
        );
    }

    public function test_archived_allows_only_a_return_to_pending(): void
    {
        $this->assertSame([AssetStatus::Pending], AssetStatus::Archived->allowedTransitions());
    }

    public function test_no_status_is_a_dead_end(): void
    {
        foreach (AssetStatus::cases() as $case) {
            $this->assertNotSame(
                [],
                $case->allowedTransitions(),
                "{$case->value} can never be left."
            );
        }
    }

    #[TestWith([AssetStatus::Pending, AssetStatus::Available])]
    #[TestWith([AssetStatus::Pending, AssetStatus::Archived])]
    #[TestWith([AssetStatus::Available, AssetStatus::Processing])]
    #[TestWith([AssetStatus::Available, AssetStatus::Approved])]
    #[TestWith([AssetStatus::Available, AssetStatus::Rejected])]
    #[TestWith([AssetStatus::Available, AssetStatus::Archived])]
    #[TestWith([AssetStatus::Processing, AssetStatus::Available])]
    #[TestWith([AssetStatus::Processing, AssetStatus::Approved])]
    #[TestWith([AssetStatus::Processing, AssetStatus::Rejected])]
    #[TestWith([AssetStatus::Approved, AssetStatus::Processing])]
    #[TestWith([AssetStatus::Approved, AssetStatus::Archived])]
    #[TestWith([AssetStatus::Rejected, AssetStatus::Pending])]
    #[TestWith([AssetStatus::Rejected, AssetStatus::Archived])]
    #[TestWith([AssetStatus::Archived, AssetStatus::Pending])]
    public function test_can_transition_to_for_valid_pairs(AssetStatus $from, AssetStatus $to): void
    {
        $this->assertTrue($from->canTransitionTo($to));
    }

    #[TestWith([AssetStatus::Pending, AssetStatus::Pending])]
    #[TestWith([AssetStatus::Available, AssetStatus::Available])]
    #[TestWith([AssetStatus::Processing, AssetStatus::Processing])]
    #[TestWith([AssetStatus::Approved, AssetStatus::Approved])]
    #[TestWith([AssetStatus::Rejected, AssetStatus::Rejected])]
    #[TestWith([AssetStatus::Archived, AssetStatus::Archived])]
    public function test_a_status_never_transitions_to_itself(AssetStatus $status): void
    {
        $this->assertFalse($status->canTransitionTo($status));
    }

    #[TestWith([AssetStatus::Pending, AssetStatus::Approved])]
    #[TestWith([AssetStatus::Pending, AssetStatus::Processing])]
    #[TestWith([AssetStatus::Pending, AssetStatus::Rejected])]
    #[TestWith([AssetStatus::Available, AssetStatus::Pending])]
    #[TestWith([AssetStatus::Processing, AssetStatus::Pending])]
    #[TestWith([AssetStatus::Processing, AssetStatus::Archived])]
    #[TestWith([AssetStatus::Approved, AssetStatus::Pending])]
    #[TestWith([AssetStatus::Approved, AssetStatus::Rejected])]
    #[TestWith([AssetStatus::Rejected, AssetStatus::Approved])]
    #[TestWith([AssetStatus::Rejected, AssetStatus::Processing])]
    #[TestWith([AssetStatus::Archived, AssetStatus::Available])]
    #[TestWith([AssetStatus::Archived, AssetStatus::Approved])]
    public function test_can_transition_to_for_rejected_pairs(AssetStatus $from, AssetStatus $to): void
    {
        $this->assertFalse($from->canTransitionTo($to));
    }
}
