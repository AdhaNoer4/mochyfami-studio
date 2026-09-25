<?php

namespace App\Enums;

enum ResearchClaimStatus: string
{
    case Unverified = 'unverified';
    case Supported = 'supported';
    case Contradicted = 'contradicted';
    case Uncertain = 'uncertain';

    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Unverified',
            self::Supported => 'Supported',
            self::Contradicted => 'Contradicted',
            self::Uncertain => 'Uncertain',
        };
    }
}
