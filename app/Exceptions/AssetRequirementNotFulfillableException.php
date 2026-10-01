<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Raised when a fulfillment is requested and the selected asset cannot carry
 * it out.
 *
 * The reasons travel with the exception rather than being flattened into the
 * message, because the caller has to be able to show a user which of several
 * problems to fix first. issues is the same array AssetCompatibilityEvaluator
 * returns, so the endpoint can publish it without re-deriving or re-wording
 * anything.
 *
 * The controller maps this to 422. Nothing was written when it is thrown:
 * selecting and fulfilling are separate actions precisely so that a failed
 * check leaves the selection intact for another attempt.
 */
class AssetRequirementNotFulfillableException extends InvalidArgumentException
{
    /**
     * @param  array<int, array{code: string, severity: string, message: string, field: string}>  $issues
     */
    public function __construct(
        string $message,
        public readonly array $issues = []
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'issues' => $this->issues,
        ];
    }
}
