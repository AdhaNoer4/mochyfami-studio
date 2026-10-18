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
}
