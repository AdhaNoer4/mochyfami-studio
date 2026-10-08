<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Raised when a TTS-generated audio asset is attached to an asset requirement
 * or selected for one.
 *
 * TTS assets may sit in the Asset Library and be previewed, but they may not
 * become fulfillment candidates: an audio generated from the plan's own
 * narration is not an interchangeable library asset. Refused with 422.
 */
class TtsAssetRequirementCandidateException extends InvalidArgumentException
{
    //
}
