<?php

namespace App\Exceptions;

use RuntimeException;

class SearchProviderException extends RuntimeException
{
    public const UNKNOWN_PROVIDER = 1000;

    public const UNAVAILABLE = 1001;

    public const TIMEOUT = 1002;

    public const AUTHENTICATION_FAILED = 1003;

    public const RATE_LIMITED = 1004;

    public const INVALID_RESPONSE = 1005;
}
