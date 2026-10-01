<?php

namespace Tests\Unit;

use App\Enums\AssetRequirementAspectRatio;
use App\Enums\AssetRequirementType;
use App\Enums\AssetType;
use App\Enums\VisualPlanItemType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssetRequirementTypeTest extends TestCase
{
    public static function acceptedTypeProvider(): array
    {
        return [
            'video accepts video' => [AssetRequirementType::Video, AssetType::Video],
            'screen recording accepts video' => [AssetRequirementType::ScreenRecording, AssetType::Video],
            'image accepts image' => [AssetRequirementType::Image, AssetType::Image],
            'graphic accepts image' => [AssetRequirementType::Graphic, AssetType::Image],
            'audio accepts audio' => [AssetRequirementType::Audio, AssetType::Audio],
        ];
    }

    #[Test]
    #[DataProvider('acceptedTypeProvider')]
    public function it_accepts_the_asset_type_it_asks_for(AssetRequirementType $requirement, AssetType $asset): void
    {
        $this->assertTrue($requirement->accepts($asset));
    }

    public static function rejectedTypeProvider(): array
    {
        return [
            'video rejects image' => [AssetRequirementType::Video, AssetType::Image],
            'video rejects audio' => [AssetRequirementType::Video, AssetType::Audio],
            'video rejects other' => [AssetRequirementType::Video, AssetType::Other],
            'screen recording rejects image' => [AssetRequirementType::ScreenRecording, AssetType::Image],
            'image rejects video' => [AssetRequirementType::Image, AssetType::Video],
            'graphic rejects video' => [AssetRequirementType::Graphic, AssetType::Video],
            'audio rejects video' => [AssetRequirementType::Audio, AssetType::Video],
            'graphic rejects audio' => [AssetRequirementType::Graphic, AssetType::Audio],
        ];
    }

    #[Test]
    #[DataProvider('rejectedTypeProvider')]
    public function it_rejects_an_asset_type_that_cannot_satisfy_it(AssetRequirementType $requirement, AssetType $asset): void
    {
        $this->assertFalse($requirement->accepts($asset));
    }

    /**
     * Other is rejected for every requirement type, including itself.
     *
     * An asset of type Other has no measurable type to check its metadata
     * against, so letting one through would make an unverifiable asset look
     * like a confirmed match.
     */
    #[Test]
    #[DataProvider('everyRequirementType')]
    public function an_other_asset_never_satisfies_anything(AssetRequirementType $requirement): void
    {
        $this->assertFalse($requirement->accepts(AssetType::Other));
    }

    /**
     * Duration only applies to media that plays. An image has no runtime, so a
     * target duration against one is a question the asset cannot answer.
     */
    #[Test]
    #[DataProvider('timeBoundProvider')]
    public function it_knows_which_requirements_are_measured_in_time(AssetRequirementType $requirement, bool $timeBound): void
    {
        $this->assertSame($timeBound, $requirement->isTimeBound());
    }

    public static function timeBoundProvider(): array
    {
        return [
            'video' => [AssetRequirementType::Video, true],
            'audio' => [AssetRequirementType::Audio, true],
            'screen recording' => [AssetRequirementType::ScreenRecording, true],
            'image' => [AssetRequirementType::Image, false],
            'graphic' => [AssetRequirementType::Graphic, false],
            'other' => [AssetRequirementType::Other, false],
        ];
    }

    public static function everyRequirementType(): array
    {
        return array_map(
            fn (AssetRequirementType $case) => [$case],
            AssetRequirementType::cases()
        );
    }

    /**
     * The visual plan mapping is a fixed editorial rule and predates this
     * part, but it is re-asserted here because the fulfillment rules lean on
     * it: a screen recording item generates a video requirement, which is why
     * accepts() maps that requirement back to a video asset.
     */
    #[Test]
    public function a_screen_recording_item_generates_a_requirement_that_accepts_video(): void
    {
        $requirement = AssetRequirementType::fromVisualType(VisualPlanItemType::ScreenRecording);

        $this->assertSame(AssetRequirementType::Video, $requirement);
        $this->assertTrue($requirement->accepts(AssetType::Video));
    }

    /**
     * The named ratios and what they measure. These are the real divisions, so
     * a 16:9 comparison is against 1.777... and not against the 1.7778 that
     * decimal:4 rounds the stored value to.
     */
    #[Test]
    public function every_named_ratio_has_its_measured_value(): void
    {
        $this->assertEqualsWithDelta(9 / 16, AssetRequirementAspectRatio::Portrait916->canonicalRatio(), 0.00001);
        $this->assertEqualsWithDelta(16 / 9, AssetRequirementAspectRatio::Landscape169->canonicalRatio(), 0.00001);
        $this->assertEqualsWithDelta(1.0, AssetRequirementAspectRatio::Square11->canonicalRatio(), 0.00001);
        $this->assertEqualsWithDelta(4 / 5, AssetRequirementAspectRatio::Portrait45->canonicalRatio(), 0.00001);
    }

    #[Test]
    public function a_ratio_matches_a_measurement_inside_the_tolerance(): void
    {
        $this->assertTrue(AssetRequirementAspectRatio::Portrait916->matches(0.5625));
        $this->assertTrue(AssetRequirementAspectRatio::Landscape169->matches(1.7778));
        $this->assertTrue(AssetRequirementAspectRatio::Square11->matches(1.0));
        $this->assertTrue(AssetRequirementAspectRatio::Portrait45->matches(0.8));
    }

    /**
     * Real footage is rarely exactly 16:9. A phone crops a hair, an editor
     * reframes, an export rounds, and refusing a correct asset over the fourth
     * decimal place would be a false negative.
     */
    #[Test]
    public function a_ratio_tolerates_a_measurement_that_is_very_slightly_off(): void
    {
        $this->assertTrue(AssetRequirementAspectRatio::Landscape169->matches(1.77));
        $this->assertTrue(AssetRequirementAspectRatio::Landscape169->matches(1.786));
        $this->assertTrue(AssetRequirementAspectRatio::Portrait916->matches(0.57));
    }

    #[Test]
    public function a_ratio_rejects_a_measurement_outside_the_tolerance(): void
    {
        $this->assertFalse(AssetRequirementAspectRatio::Portrait916->matches(1.7778));
        $this->assertFalse(AssetRequirementAspectRatio::Landscape169->matches(1.0));
        $this->assertFalse(AssetRequirementAspectRatio::Square11->matches(0.5625));
        $this->assertFalse(AssetRequirementAspectRatio::Portrait45->matches(16 / 9));
    }

    /**
     * Missing is not zero and is not a portrait shape. Treating an absent
     * measurement as a pass would let an uninspected asset satisfy a shape
     * requirement.
     */
    #[Test]
    public function a_ratio_never_matches_a_missing_measurement(): void
    {
        foreach (AssetRequirementAspectRatio::cases() as $case) {
            $this->assertFalse($case->matches(null));
        }
    }
}
