<?php

namespace Tests\Unit;

use App\Enums\TtsTrackSource;
use Tests\TestCase;

class TtsTrackSourceTest extends TestCase
{
    public function test_exposes_all_expected_cases(): void
    {
        $this->assertSame(
            ['tts', 'manual'],
            array_column(TtsTrackSource::cases(), 'value')
        );
    }

    public function test_every_case_has_a_label(): void
    {
        foreach (TtsTrackSource::cases() as $case) {
            $this->assertNotSame('', $case->label());
        }
    }
}
