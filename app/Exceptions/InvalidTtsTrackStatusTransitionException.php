<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Raised when a TTS track is asked to move somewhere its state graph forbids.
 *
 * The graph lives on TtsTrackStatus and there is intentionally no public
 * status endpoint, so this is only reachable through the internal lifecycle:
 * archiving an already archived track is the ordinary case. Refused with 422.
 */
class InvalidTtsTrackStatusTransitionException extends InvalidArgumentException
{
    //
}
