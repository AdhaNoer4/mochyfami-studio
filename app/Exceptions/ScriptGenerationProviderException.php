<?php

namespace App\Exceptions;

use RuntimeException;

class ScriptGenerationProviderException extends RuntimeException
{
    public const UNKNOWN_PROVIDER = 2000;

    public const UNAVAILABLE = 2001;

    public const INVALID_RESPONSE = 2002;
}
