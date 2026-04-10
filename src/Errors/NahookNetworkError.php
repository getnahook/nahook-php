<?php

declare(strict_types=1);

namespace Nahook\Errors;

/**
 * Network-level failure (no HTTP response received).
 */
final class NahookNetworkError extends NahookError
{
    public readonly \Throwable $cause;

    public function __construct(\Throwable $cause)
    {
        parent::__construct('Network error: ' . $cause->getMessage(), 0, $cause);
        $this->cause = $cause;
    }
}
