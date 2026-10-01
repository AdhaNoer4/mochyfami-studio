<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Raised when a fulfillment is requested with no asset selected.
 *
 * This is separate from AssetRequirementNotFulfillableException because it is
 * not a failed check, it is a missing step. Nothing was evaluated, so there
 * are no issues to publish; the client is being told to select first. The
 * controller maps it to 422 for consistency with every other refusal to
 * fulfill, while keeping the message distinct enough to act on.
 */
class AssetNotSelectedException extends InvalidArgumentException
{
    //
}
