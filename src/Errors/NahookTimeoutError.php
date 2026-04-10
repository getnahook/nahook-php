<?php

declare(strict_types=1);

namespace Nahook\Errors;

/**
 * Request timed out.
 */
class NahookTimeoutError extends NahookError
{
    public readonly int $timeoutMs;

    public function __construct(int $timeoutMs)
    {
        parent::__construct("Request timed out after {$timeoutMs}ms");
        $this->timeoutMs = $timeoutMs;
    }
}
