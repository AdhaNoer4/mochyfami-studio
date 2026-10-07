<?php

namespace App\Exceptions;

use RuntimeException;

class TtsProviderException extends RuntimeException
{
    public const UNKNOWN_PROVIDER = 4000;

    public const UNAVAILABLE = 4001;

    public const INVALID_RESPONSE = 4002;
}
