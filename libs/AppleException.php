<?php

declare(strict_types=1);

namespace SymconiCloud;

/** Contains only fixed, non-sensitive error identifiers. */
final class AppleException extends \RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $httpStatus = 0, public readonly int $retryAfter = 0)
    {
        parent::__construct($reason);
    }
}
