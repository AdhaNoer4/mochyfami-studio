<?php

namespace App\Exceptions;

use RuntimeException;

class ScriptGenerationNotReadyException extends RuntimeException
{
    public function __construct(string $message = 'Research must pass the quality gate before AI script generation.')
    {
        parent::__construct($message);
    }
}
