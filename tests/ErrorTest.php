<?php

declare(strict_types=1);

namespace Nahook\Tests;

use Nahook\Errors\NahookAPIError;
use Nahook\Errors\NahookNetworkError;
use Nahook\Errors\NahookTimeoutError;
use PHPUnit\Framework\TestCase;

class ErrorTest extends TestCase
{
    // -- isRetryable ----------------------------------------------------------

    public function testRetryableOn500(): void
    {
        $error = new NahookAPIError(500, 'internal_error', 'Server error');
        $this->assertTrue($error->isRetryable());
    }

    public function testRetryableOn429(): void
    {
        $error = new NahookAPIError(429, 'rate_limited', 'Too many requests', 5);
        $this->assertTrue($error->isRetryable());
    }

    public function testNotRetryableOn404(): void
    {
        $error = new NahookAPIError(404, 'not_found', 'Not found');
        $this->assertFalse($error->isRetryable());
    }

    // -- isAuthError ----------------------------------------------------------

    public function testAuthErrorOn401(): void
    {
        $error = new NahookAPIError(401, 'unauthorized', 'Invalid token');
        $this->assertTrue($error->isAuthError());
    }

    public function testAuthErrorOn403TokenDisabled(): void
    {
        $error = new NahookAPIError(403, 'token_disabled', 'Token disabled');
        $this->assertTrue($error->isAuthError());
    }

    public function testNotAuthErrorOn403Other(): void
    {
        $error = new NahookAPIError(403, 'forbidden', 'Forbidden');
        $this->assertFalse($error->isAuthError());
    }

    // -- isNotFound -----------------------------------------------------------

    public function testNotFoundOn404(): void
    {
        $error = new NahookAPIError(404, 'not_found', 'Resource not found');
        $this->assertTrue($error->isNotFound());
    }

    public function testNotNotFoundOn500(): void
    {
        $error = new NahookAPIError(500, 'internal_error', 'Server error');
        $this->assertFalse($error->isNotFound());
    }

    // -- isRateLimited --------------------------------------------------------

    public function testRateLimitedOn429(): void
    {
        $error = new NahookAPIError(429, 'rate_limited', 'Too many requests', 10);
        $this->assertTrue($error->isRateLimited());
        $this->assertSame(10, $error->retryAfter);
    }

    public function testNotRateLimitedOn500(): void
    {
        $error = new NahookAPIError(500, 'internal_error', 'Server error');
        $this->assertFalse($error->isRateLimited());
    }

    // -- isValidationError ----------------------------------------------------

    public function testValidationErrorOn400(): void
    {
        $error = new NahookAPIError(400, 'validation_error', 'Bad request');
        $this->assertTrue($error->isValidationError());
    }

    public function testNotValidationErrorOn401(): void
    {
        $error = new NahookAPIError(401, 'unauthorized', 'Unauthorized');
        $this->assertFalse($error->isValidationError());
    }

    // -- NetworkError ---------------------------------------------------------

    public function testNetworkErrorWrapsOriginalCause(): void
    {
        $cause = new \RuntimeException('connection refused');
        $error = new NahookNetworkError($cause);
        $this->assertSame($cause, $error->cause);
        $this->assertStringContainsString('connection refused', $error->getMessage());
    }

    // -- TimeoutError ---------------------------------------------------------

    public function testTimeoutErrorStoresTimeoutMs(): void
    {
        $error = new NahookTimeoutError(15000);
        $this->assertSame(15000, $error->timeoutMs);
        $this->assertStringContainsString('15000ms', $error->getMessage());
    }
}
