<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Raised when a selection names an asset that is not a candidate.
 *
 * Distinct from AssetNotSelectedException because the two failures point in
 * opposite directions: that one is a requirement with nothing chosen, this one
 * is a choice the requirement never offered. Both are refused with 422.
 */
class AssetNotCandidateException extends InvalidArgumentException
{
    //
}
