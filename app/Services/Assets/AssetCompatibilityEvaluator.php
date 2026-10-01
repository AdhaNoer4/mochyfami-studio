<?php

namespace App\Services\Assets;

use App\Enums\AssetMetadataStatus;
use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\AssetRequirement;

/**
 * Decides whether one asset could satisfy one requirement, and says why not.
 *
 * Every check here is a comparison between two rows this project already
 * holds. There is no model call, no external lookup, no search, and no
 * scoring: the same requirement and the same asset always produce the same
 * answer, in the same order, with the same wording. That is the whole point of
 * this class. Deciding whether footage fits is an editorial judgement that
 * belongs to a person, and this only reports what can be established about
 * two records.
 *
 * The result separates blockers from warnings rather than returning a single
 * boolean, because a candidate that is the wrong shape is a different problem
 * from a candidate that is longer than the target, and a caller needs to be
 * able to say which. Blockers are the only thing that stops a fulfillment.
 * A warning is information about a fit that still passed, and it is why
 * compatible can be true while issues is not empty.
 */
class AssetCompatibilityEvaluator
{
    /**
     * The status an asset must be in before it can fulfill anything.
     *
     * Rejected is excluded because a person already decided this asset is
     * unusable and a technical check quietly overriding that would undo their
     * decision. Pending and Processing are excluded because neither means the
     * asset has finished being worked on. Available, Approved, and Archived
     * all mean it has.
     */
    private const USABLE_STATUSES = [
        AssetStatus::Available,
        AssetStatus::Approved,
        AssetStatus::Archived,
    ];

    /**
     * Evaluate one candidate against one requirement.
     *
     * Issues come back in a fixed order, from the most fundamental
     * disagreement to the most marginal one, so a client can render the first
     * as a headline and the rest as detail without sorting anything itself.
     *
     * @return array{compatible: bool, issues: array<int, array{code: string, severity: string, message: string, field: string}>}
     */
    public function evaluate(AssetRequirement $requirement, Asset $asset): array
    {
        $typeIssues = $this->typeIssues($requirement, $asset);
        $usabilityIssues = $this->usabilityIssues($asset);
        $metadataIssues = $this->metadataIssues($asset);

        // Nothing measured is compared when there was no trustworthy
        // measurement to compare. An uninspected asset, or one with no file at
        // all, reports whatever the row happens to say, which may describe an
        // older file or nothing at all. One blocker saying so is both true and
        // enough to act on, and piling a ratio complaint on top of it would be
        // describing numbers nobody ever produced.
        $measured = $asset->hasFileAttached()
            && $asset->metadata_status === AssetMetadataStatus::Completed;

        $issues = array_merge(
            $typeIssues,
            $usabilityIssues,
            $metadataIssues,
            $measured ? $this->shapeIssues($requirement, $asset) : [],
            $measured ? $this->durationIssues($requirement, $asset) : [],
        );

        $hasBlocker = collect($issues)->contains(fn (array $issue) => $issue['severity'] === 'blocker');

        return [
            'compatible' => ! $hasBlocker,
            'issues' => $issues,
        ];
    }

    /**
     * Whether the asset is even the kind of thing this requirement asked for.
     *
     * This comes first because a type mismatch makes every later check
     * meaningless: a duration cannot be wrong for an audio file standing in
     * for a video.
     *
     * @return array<int, array{code: string, severity: string, message: string, field: string}>
     */
    private function typeIssues(AssetRequirement $requirement, Asset $asset): array
    {
        if ($requirement->requirement_type->accepts($asset->type)) {
            return [];
        }

        if ($asset->type === AssetType::Other) {
            return [$this->issue(
                'ASSET_TYPE_OTHER',
                'blocker',
                'This asset has no measurable type, so it cannot be verified as a fit for this requirement.',
                'type'
            )];
        }

        return [$this->issue(
            'TYPE_MISMATCH',
            'blocker',
            "This requirement needs a {$requirement->requirement_type->label()} asset, but this asset is a {$asset->type->label()}.",
            'type'
        )];
    }

    /**
     * Whether the asset is in a state where it could be used at all.
     *
     * The two failures are separate codes because they are separate problems.
     * A missing file is an asset that was never filled in; an unusable status
     * is a file that exists but that somebody has already ruled out. Fixing
     * one does not fix the other.
     *
     * @return array<int, array{code: string, severity: string, message: string, field: string}>
     */
    private function usabilityIssues(Asset $asset): array
    {
        $issues = [];

        if (! $asset->hasFileAttached()) {
            $issues[] = $this->issue(
                'ASSET_HAS_NO_FILE',
                'blocker',
                'This asset has no file attached, so there is nothing to check against the requirement.',
                'file'
            );
        }

        if (! in_array($asset->status, self::USABLE_STATUSES, true)) {
            $issues[] = $this->issue(
                'ASSET_NOT_USABLE',
                'blocker',
                "This asset is {$asset->status->label()}, so it cannot fulfill a requirement until it is usable.",
                'status'
            );
        }

        return $issues;
    }

    /**
     * Whether the recorded technical metadata can be believed.
     *
     * A completed status is the precondition for trusting anything below, so
     * an unverified asset is stopped here rather than being measured against
     * dimensions that may describe a file it no longer has.
     *
     * @return array<int, array{code: string, severity: string, message: string, field: string}>
     */
    private function metadataIssues(Asset $asset): array
    {
        if (! $asset->hasFileAttached()) {
            return [];
        }

        if ($asset->metadata_status !== AssetMetadataStatus::Completed) {
            $status = $asset->metadata_status;

            return [$this->issue(
                'METADATA_NOT_VERIFIED',
                'blocker',
                $status === AssetMetadataStatus::Failed
                    ? 'This asset\'s file could not be read, so its technical details cannot be verified.'
                    : 'This asset\'s technical details have not been verified yet.',
                'metadata_status'
            )];
        }

        return [];
    }

    /**
     * Whether the asset's measured shape matches the required ratio.
     *
     * The missing-dimensions check is reported separately from the mismatch
     * check because they call for different fixes: one needs the file
     * re-inspected, the other needs a different file.
     *
     * @return array<int, array{code: string, severity: string, message: string, field: string}>
     */
    private function shapeIssues(AssetRequirement $requirement, Asset $asset): array
    {
        if ($requirement->aspect_ratio === null) {
            return [];
        }

        if ($asset->width === null || $asset->height === null) {
            return [$this->issue(
                'METADATA_DIMENSIONS_MISSING',
                'blocker',
                "This requirement needs a {$requirement->aspect_ratio->value} asset, but this asset's dimensions are unknown.",
                'width'
            )];
        }

        if ($requirement->aspect_ratio->matches((float) $asset->aspect_ratio)) {
            return [];
        }

        return [$this->issue(
            'ASPECT_RATIO_MISMATCH',
            'blocker',
            "This requirement needs a {$requirement->aspect_ratio->value} asset, but this asset measures ".$this->describeRatio((float) $asset->aspect_ratio).'.',
            'aspect_ratio'
        )];
    }

    /**
     * Whether the asset runs long enough, and whether it runs too long.
     *
     * The asymmetry is the reason these are two codes. Footage that is shorter
     * than the target cannot be stretched to fill it without looking wrong, so
     * it blocks. Footage that is longer can simply be cut down, so it warns
     * and the user decides. Blocking on both would make the evaluator refuse
     * perfectly usable material, which is a judgement this class has no
     * business making.
     *
     * @return array<int, array{code: string, severity: string, message: string, field: string}>
     */
    private function durationIssues(AssetRequirement $requirement, Asset $asset): array
    {
        if (! $requirement->requirement_type->isTimeBound()) {
            return [];
        }

        $target = $requirement->target_duration_seconds;

        if ($target === null) {
            return [];
        }

        if ($asset->duration_seconds === null) {
            return [$this->issue(
                'METADATA_DURATION_MISSING',
                'blocker',
                'This asset\'s duration is unknown, so it cannot be checked against the target duration.',
                'duration_seconds'
            )];
        }

        if ($asset->duration_seconds < $target) {
            return [$this->issue(
                'DURATION_TOO_SHORT',
                'blocker',
                "This asset runs {$asset->duration_seconds}s, shorter than the {$target}s target.",
                'duration_seconds'
            )];
        }

        if ($asset->duration_seconds > $target) {
            return [$this->issue(
                'DURATION_EXCEEDS_TARGET',
                'warning',
                "This asset runs {$asset->duration_seconds}s against a {$target}s target and will need trimming.",
                'duration_seconds'
            )];
        }

        return [];
    }

    /**
     * A measured ratio as a short fraction, so the message does not make the
     * user do arithmetic to see how far off it is.
     */
    private function describeRatio(float $ratio): string
    {
        return match (true) {
            $ratio >= 1.7 && $ratio <= 1.9 => 'roughly 16:9',
            $ratio >= 0.52 && $ratio <= 0.62 => 'roughly 9:16',
            $ratio >= 0.75 && $ratio <= 0.85 => 'roughly 4:5',
            $ratio >= 0.95 && $ratio <= 1.05 => 'roughly 1:1',
            default => 'a different ratio',
        };
    }

    /**
     * @return array{code: string, severity: string, message: string, field: string}
     */
    private function issue(string $code, string $severity, string $message, string $field): array
    {
        return compact('code', 'severity', 'message', 'field');
    }
}
