<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Raised when a manual dubbing track is asked to move its approval status
 * somewhere the state graph forbids.
 *
 * The graph lives on ManualDubbingApprovalStatus: a draft is submitted once,
 * the reviewer approves or rejects it, and both outcomes are terminal.
 * Submitting an already reviewed track, or reviewing an unsubmitted one, is
 * refused with 422 rather than silently ignored.
 */
class InvalidManualApprovalStatusTransitionException extends InvalidArgumentException
{
    //
}
