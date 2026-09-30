<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Raised when an asset is already associated with the requirement it is being
 * attached to.
 *
 * This mirrors DuplicateScriptResearchClaimException, which is the
 * repository's existing answer to the same domain problem on the research
 * traceability pivot. The controller maps it to 409 Conflict.
 */
class DuplicateAssetRequirementAssetException extends InvalidArgumentException
{
    //
}
