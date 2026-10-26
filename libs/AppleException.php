<?php

declare(strict_types=1);

namespace SymconiCloud;

/** Contains only fixed, non-sensitive error identifiers. */
final class AppleException extends \RuntimeException
{
    public readonly string $appleCode;

    public function __construct(public readonly string $reason, public readonly int $httpStatus = 0, public readonly int $retryAfter = 0, string $appleCode = '')
    {
        $this->appleCode = in_array($appleCode, ['-20209', '-20101'], true) ? $appleCode : '';
        parent::__construct($reason);
    }
}
