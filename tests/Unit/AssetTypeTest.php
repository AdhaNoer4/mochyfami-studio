<?php

namespace Tests\Unit;

use App\Enums\AssetType;
use Tests\TestCase;

class AssetTypeTest extends TestCase
{
    public function test_exposes_all_expected_cases(): void
    {
        $this->assertSame(
            ['video', 'image', 'audio', 'other'],
            array_column(AssetType::cases(), 'value')
        );
    }

    public function test_every_case_has_a_label(): void
    {
        foreach (AssetType::cases() as $case) {
            $this->assertNotSame('', $case->label());
        }
    }
}
