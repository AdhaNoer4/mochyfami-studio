<?php

namespace Tests\Unit;

use App\Enums\AssetMetadataStatus;
use App\Enums\AssetRequirementAspectRatio;
use App\Enums\AssetRequirementStatus;
use App\Enums\AssetRequirementType;
use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\AssetRequirement;
use App\Services\Assets\AssetCompatibilityEvaluator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The evaluator is the whole of what "fits" means in this part, so these
 * tests pin down every code it can emit and the fact that it emits them in a
 * fixed order.
 */
class AssetCompatibilityEvaluatorTest extends TestCase
{
    private AssetCompatibilityEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evaluator = app(AssetCompatibilityEvaluator::class);
    }

    /**
     * The baseline everything else is measured against: a verified portrait
     * video against a portrait video requirement with a target it meets exactly.
     */
    #[Test]
    public function a_verified_matching_asset_is_compatible_with_no_issues(): void
    {
        $requirement = $this->requirement();
        $asset = $this->asset();

        $result = $this->evaluator->evaluate($requirement, $asset);

        $this->assertTrue($result['compatible']);
        $this->assertSame([], $result['issues']);
    }

    #[Test]
    public function an_audio_asset_does_not_satisfy_a_video_requirement(): void
    {
        $result = $this->evaluator->evaluate(
            $this->requirement(),
            $this->asset(['type' => AssetType::Audio, 'mime_type' => 'audio/mpeg'])
        );

        $this->assertFalse($result['compatible']);
        $this->assertContains('TYPE_MISMATCH', $this->codes($result));
    }

    /**
     * An Other asset is called out separately from a plain mismatch. It has no
     * measurable type at all, which is a different problem from being the
     * wrong kind of file.
     */
    #[Test]
    public function an_other_asset_gets_its_own_issue_code(): void
    {
        $result = $this->evaluator->evaluate(
            $this->requirement(),
            $this->asset(['type' => AssetType::Other, 'mime_type' => null])
        );

        $this->assertFalse($result['compatible']);
        $this->assertContains('ASSET_TYPE_OTHER', $this->codes($result));
        $this->assertNotContains('TYPE_MISMATCH', $this->codes($result));
    }

    /**
     * A graphic requirement is satisfied by any image, and a screen recording
     * by any video. A requirement distinguishes them because they are
     * different editorial asks; an asset cannot.
     */
    #[Test]
    public function a_graphic_requirement_accepts_an_image_asset(): void
    {
        $requirement = $this->requirement([
            'requirement_type' => AssetRequirementType::Graphic,
            'target_duration_seconds' => null,
        ]);

        $result = $this->evaluator->evaluate($requirement, $this->asset(['type' => AssetType::Image]));

        $this->assertTrue($result['compatible']);
    }

    #[Test]
    public function a_screen_recording_requirement_accepts_a_video_asset(): void
    {
        $requirement = $this->requirement([
            'requirement_type' => AssetRequirementType::ScreenRecording,
        ]);

        $result = $this->evaluator->evaluate($requirement, $this->asset());

        $this->assertTrue($result['compatible']);
    }

    #[Test]
    public function an_asset_without_a_file_cannot_satisfy_anything(): void
    {
        $result = $this->evaluator->evaluate(
            $this->requirement(),
            $this->asset(['file_path' => null, 'metadata_status' => null])
        );

        $this->assertFalse($result['compatible']);
        $this->assertContains('ASSET_HAS_NO_FILE', $this->codes($result));
    }

    /**
     * A blank path is not a path. A requirement with whitespace in the column
     * would otherwise look like it had a file.
     */
    #[Test]
    public function a_blank_file_path_counts_as_no_file(): void
    {
        $result = $this->evaluator->evaluate($this->requirement(), $this->asset(['file_path' => '   ']));

        $this->assertFalse($result['compatible']);
        $this->assertContains('ASSET_HAS_NO_FILE', $this->codes($result));
    }

    /**
     * A missing file and an unusable status are separate problems with
     * separate fixes, so they get separate codes.
     */
    #[Test]
    public function a_rejected_asset_cannot_satisfy_a_requirement(): void
    {
        $result = $this->evaluator->evaluate(
            $this->requirement(),
            $this->asset(['status' => AssetStatus::Rejected])
        );

        $this->assertFalse($result['compatible']);
        $this->assertContains('ASSET_NOT_USABLE', $this->codes($result));
    }

    #[Test]
    public function a_processing_asset_cannot_satisfy_a_requirement_yet(): void
    {
        $result = $this->evaluator->evaluate(
            $this->requirement(),
            $this->asset(['status' => AssetStatus::Processing])
        );

        $this->assertFalse($result['compatible']);
        $this->assertContains('ASSET_NOT_USABLE', $this->codes($result));
    }

    /**
     * An archived asset has finished being worked on, so nothing about its
     * state says it is unusable.
     */
    #[Test]
    public function an_archived_asset_is_still_usable(): void
    {
        $result = $this->evaluator->evaluate(
            $this->requirement(),
            $this->asset(['status' => AssetStatus::Archived])
        );

        $this->assertTrue($result['compatible']);
    }

    /**
     * A completed status is the precondition for believing anything else the
     * asset reports. An unverified asset is stopped before it is measured.
     */
    #[Test]
    public function unverified_metadata_blocks_the_asset(): void
    {
        $result = $this->evaluator->evaluate(
            $this->requirement(),
            $this->asset(['metadata_status' => AssetMetadataStatus::Pending])
        );

        $this->assertFalse($result['compatible']);
        $this->assertContains('METADATA_NOT_VERIFIED', $this->codes($result));
    }

    #[Test]
    public function unreadable_metadata_blocks_the_asset(): void
    {
        $result = $this->evaluator->evaluate(
            $this->requirement(),
            $this->asset(['metadata_status' => AssetMetadataStatus::Failed, 'metadata_error' => 'Unreadable.'])
        );

        $this->assertFalse($result['compatible']);
        $this->assertContains('METADATA_NOT_VERIFIED', $this->codes($result));
    }

    /**
     * A file that has not been read yet has no dimensions, so reporting a ratio
     * mismatch on top of that would describe a measurement nobody made.
     */
    #[Test]
    public function unverified_metadata_is_not_also_reported_as_a_shape_problem(): void
    {
        $result = $this->evaluator->evaluate(
            $this->requirement(),
            $this->asset(['metadata_status' => AssetMetadataStatus::Pending, 'width' => null, 'height' => null])
        );

        $this->assertSame(['METADATA_NOT_VERIFIED'], $this->codes($result));
    }

    /**
     * Missing dimensions and a wrong ratio call for different fixes, so they
     * are told apart: one needs the file re-inspected, the other needs a
     * different file.
     */
    #[Test]
    public function unknown_dimensions_are_reported_as_missing_rather_than_mismatched(): void
    {
        $result = $this->evaluator->evaluate(
            $this->requirement(),
            $this->asset(['width' => null, 'height' => null])
        );

        $this->assertFalse($result['compatible']);
        $this->assertContains('METADATA_DIMENSIONS_MISSING', $this->codes($result));
        $this->assertNotContains('ASPECT_RATIO_MISMATCH', $this->codes($result));
    }

    #[Test]
    public function a_landscape_asset_does_not_satisfy_a_portrait_requirement(): void
    {
        $result = $this->evaluator->evaluate(
            $this->requirement(),
            $this->asset(['width' => 1920, 'height' => 1080, 'aspect_ratio' => 1.7778])
        );

        $this->assertFalse($result['compatible']);
        $this->assertContains('ASPECT_RATIO_MISMATCH', $this->codes($result));
    }

    /**
     * A phone crop or an export rounding is not a different shape.
     */
    #[Test]
    public function a_slightly_off_ratio_still_satisfies_the_requirement(): void
    {
        $result = $this->evaluator->evaluate(
            $this->requirement(),
            $this->asset(['width' => 1088, 'height' => 1920, 'aspect_ratio' => 0.5667])
        );

        $this->assertTrue($result['compatible']);
    }

    /**
     * A requirement with no aspect ratio asks for no shape, so nothing is
     * checked and a wide asset is fine.
     */
    #[Test]
    public function no_required_ratio_means_no_shape_check(): void
    {
        $requirement = $this->requirement(['aspect_ratio' => null]);

        $result = $this->evaluator->evaluate(
            $requirement,
            $this->asset(['width' => 1920, 'height' => 1080, 'aspect_ratio' => 1.7778])
        );

        $this->assertTrue($result['compatible']);
    }

    #[Test]
    public function an_unknown_duration_blocks_a_time_bound_requirement(): void
    {
        $result = $this->evaluator->evaluate(
            $this->requirement(),
            $this->asset(['duration_seconds' => null])
        );

        $this->assertFalse($result['compatible']);
        $this->assertContains('METADATA_DURATION_MISSING', $this->codes($result));
    }

    /**
     * Footage shorter than the target cannot be stretched to fill it without
     * looking wrong, so it blocks.
     */
    #[Test]
    public function an_asset_shorter_than_the_target_blocks_the_fulfillment(): void
    {
        $result = $this->evaluator->evaluate(
            $this->requirement(),
            $this->asset(['duration_seconds' => 20])
        );

        $this->assertFalse($result['compatible']);
        $this->assertContains('DURATION_TOO_SHORT', $this->codes($result));
    }

    /**
     * Footage longer than the target can simply be cut down, so it warns. This
     * is why compatible can be true while issues is not empty.
     */
    #[Test]
    public function an_asset_longer_than_the_target_only_warns(): void
    {
        $result = $this->evaluator->evaluate(
            $this->requirement(),
            $this->asset(['duration_seconds' => 45])
        );

        $this->assertTrue($result['compatible']);
        $this->assertSame(['DURATION_EXCEEDS_TARGET'], $this->codes($result));
        $this->assertSame('warning', $result['issues'][0]['severity']);
    }

    #[Test]
    public function a_duration_matching_the_target_exactly_raises_nothing(): void
    {
        $result = $this->evaluator->evaluate(
            $this->requirement(),
            $this->asset(['duration_seconds' => 30])
        );

        $this->assertSame([], $result['issues']);
    }

    /**
     * An image has no runtime at all, so a target duration against one is a
     * question the asset cannot answer either way and is not treated as a
     * failure.
     */
    #[Test]
    public function a_duration_is_not_checked_against_a_non_time_bound_requirement(): void
    {
        $requirement = $this->requirement(['requirement_type' => AssetRequirementType::Image]);

        $result = $this->evaluator->evaluate(
            $requirement,
            $this->asset(['type' => AssetType::Image, 'duration_seconds' => null])
        );

        $this->assertTrue($result['compatible']);
    }

    #[Test]
    public function a_requirement_with_no_target_duration_skips_the_duration_check(): void
    {
        $result = $this->evaluator->evaluate(
            $this->requirement(['target_duration_seconds' => null]),
            $this->asset(['duration_seconds' => null])
        );

        $this->assertTrue($result['compatible']);
    }

    /**
     * The order is from the most fundamental disagreement to the most marginal,
     * so a client can render the first as a headline without sorting.
     */
    #[Test]
    public function issues_come_back_in_a_fixed_order(): void
    {
        $result = $this->evaluator->evaluate(
            $this->requirement(['aspect_ratio' => AssetRequirementAspectRatio::Landscape169]),
            $this->asset([
                'type' => AssetType::Image,
                'status' => AssetStatus::Processing,
                'file_path' => null,
                'metadata_status' => null,
            ])
        );

        $this->assertSame([
            'TYPE_MISMATCH',
            'ASSET_HAS_NO_FILE',
            'ASSET_NOT_USABLE',
        ], $this->codes($result));
    }

    /**
     * The evaluator decides nothing on its own, so the same two rows must
     * always produce the same answer. There is no scoring to drift and nothing
     * to learn.
     */
    #[Test]
    public function the_same_two_rows_always_produce_the_same_answer(): void
    {
        $requirement = $this->requirement();
        $asset = $this->asset();

        $first = $this->evaluator->evaluate($requirement, $asset);
        $second = $this->evaluator->evaluate($requirement, $asset);

        $this->assertSame($first, $second);
    }

    #[Test]
    public function every_issue_carries_a_code_a_severity_a_message_and_a_field(): void
    {
        $result = $this->evaluator->evaluate(
            $this->requirement(),
            $this->asset(['status' => AssetStatus::Rejected, 'duration_seconds' => 5])
        );

        $this->assertNotSame([], $result['issues']);

        foreach ($result['issues'] as $issue) {
            $this->assertSame(['code', 'severity', 'message', 'field'], array_keys($issue));
            $this->assertNotSame('', $issue['code']);
            $this->assertContains($issue['severity'], ['blocker', 'warning']);
            $this->assertNotSame('', $issue['message']);
            $this->assertNotSame('', $issue['field']);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function requirement(array $overrides = []): AssetRequirement
    {
        $requirement = new AssetRequirement(array_merge([
            'requirement_type' => AssetRequirementType::Video,
            'description' => 'A portrait shot.',
            'target_duration_seconds' => 30,
            'aspect_ratio' => AssetRequirementAspectRatio::Portrait916,
        ], $overrides));

        // The status cast is a read path, so an unsaved model still needs one
        // for the evaluator to be able to say anything about the workflow.
        $requirement->status = AssetRequirementStatus::Searching;

        return $requirement;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function asset(array $overrides = []): Asset
    {
        $asset = new Asset;

        // forceFill, not fill. aspect_ratio and metadata_status are
        // deliberately not fillable on the model because they may only be
        // written by inspecting a stored file, and that rule is correct. It
        // does not apply here: these fixtures stand in for rows inspection
        // has already written, and going through fill would silently drop the
        // measured values and test the wrong thing.
        $asset->forceFill(array_merge([
            'type' => AssetType::Video,
            'status' => AssetStatus::Available,
            'file_path' => 'projects/abc/asset.mp4',
            'duration_seconds' => 30,
            'width' => 1080,
            'height' => 1920,
            'aspect_ratio' => 0.5625,
            'metadata_status' => AssetMetadataStatus::Completed,
        ], $overrides));

        return $asset;
    }

    /**
     * @param  array{compatible: bool, issues: array<int, array<string, string>>}  $result
     * @return array<int, string>
     */
    private function codes(array $result): array
    {
        return array_column($result['issues'], 'code');
    }
}
