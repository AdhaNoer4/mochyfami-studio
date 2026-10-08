<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Raised when a TTS synthesis is requested for a narration the pipeline
 * cannot handle.
 *
 * The provider is never called for a narration this refuses; refusing leaves
 * no track behind, so a failed request is not the same as a generation that
 * ran and failed. Refused with 422.
 */
class InvalidTtsNarrationException extends InvalidArgumentException
{
    //
}
