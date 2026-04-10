<?php

declare(strict_types=1);

namespace Nahook\Errors;

/**
 * API returned an error response (4xx/5xx).
 */
final class NahookAPIError extends NahookError
{
    public readonly int $status;
    public readonly string $errorCode;
    public readonly ?int $retryAfter;

    public function __construct(int $status, string $errorCode, string $message, ?int $retryAfter = null)
    {
        parent::__construct($message);
        $this->status = $status;
        $this->errorCode = $errorCode;
        $this->retryAfter = $retryAfter;
    }

    public function isRetryable(): bool
    {
        return $this->status >= 500 || $this->status === 429;
    }

    public function isAuthError(): bool
    {
        return $this->status === 401 || ($this->status === 403 && $this->errorCode === 'token_disabled');
    }

    public function isNotFound(): bool
    {
        return $this->status === 404;
    }

    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }

    public function isValidationError(): bool
    {
        return $this->status === 400;
    }
}
