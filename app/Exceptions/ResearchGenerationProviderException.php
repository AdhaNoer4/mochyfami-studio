<?php

namespace App\Exceptions;

use RuntimeException;

class ResearchGenerationProviderException extends RuntimeException
{
    public const UNKNOWN_PROVIDER = 3000;

    public const UNAVAILABLE = 3001;

    public const INVALID_RESPONSE = 3002;
}
