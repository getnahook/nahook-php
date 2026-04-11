<?php

declare(strict_types=1);

namespace Nahook\Tests;

use Nahook\Errors\NahookAPIError;
use Nahook\Errors\NahookError;
use Nahook\Errors\NahookNetworkError;
use Nahook\Errors\NahookTimeoutError;
use Nahook\NahookClient;
use Nahook\NahookManagement;
use PHPUnit\Framework\TestCase;

class ClientTest extends TestCase
{
    // ── API Key Validation ──

    public function testRejectsInvalidApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new NahookClient('bad_key_without_prefix');
    }

    public function testAcceptsValidApiKey(): void
    {
        $client = new NahookClient('nhk_us_test123', [
            'baseUrl' => 'https://api.test.com',
        ]);
        $this->assertNotNull($client);
    }

    // ── Management Token Validation ──

    public function testRejectsInvalidManagementToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new NahookManagement('bad_token_without_prefix');
    }

    public function testAcceptsValidManagementToken(): void
    {
        $mgmt = new NahookManagement('nhm_test123', [
            'baseUrl' => 'https://api.test.com',
        ]);
        $this->assertNotNull($mgmt);
    }

    // ── Management Resources ──

    public function testManagementHasAllResources(): void
    {
        $mgmt = new NahookManagement('nhm_test123', [
            'baseUrl' => 'https://api.test.com',
        ]);

        $this->assertNotNull($mgmt->endpoints);
        $this->assertNotNull($mgmt->eventTypes);
        $this->assertNotNull($mgmt->applications);
        $this->assertNotNull($mgmt->subscriptions);
        $this->assertNotNull($mgmt->portalSessions);
    }

    // ── Exception Hierarchy ──

    public function testExceptionHierarchy(): void
    {
        $apiError = new NahookAPIError(500, 'internal', 'Server error');
        $networkError = new NahookNetworkError(new \RuntimeException('Connection refused'));
        $timeoutError = new NahookTimeoutError(5000);

        // All extend NahookError
        $this->assertInstanceOf(NahookError::class, $apiError);
        $this->assertInstanceOf(NahookError::class, $networkError);
        $this->assertInstanceOf(NahookError::class, $timeoutError);

        // NahookError extends RuntimeException
        $this->assertInstanceOf(\RuntimeException::class, $apiError);
        $this->assertInstanceOf(\RuntimeException::class, $networkError);
        $this->assertInstanceOf(\RuntimeException::class, $timeoutError);
    }

    // ── API Error Helpers ──

    public function testApiExceptionIsRetryable(): void
    {
        $error500 = new NahookAPIError(500, 'internal', 'Server error');
        $this->assertTrue($error500->isRetryable());

        $error429 = new NahookAPIError(429, 'rate_limited', 'Too many requests');
        $this->assertTrue($error429->isRetryable());

        $error400 = new NahookAPIError(400, 'validation', 'Bad request');
        $this->assertFalse($error400->isRetryable());

        $error404 = new NahookAPIError(404, 'not_found', 'Not found');
        $this->assertFalse($error404->isRetryable());
    }

    public function testApiExceptionIsAuthError(): void
    {
        $error401 = new NahookAPIError(401, 'unauthorized', 'Unauthorized');
        $this->assertTrue($error401->isAuthError());

        $error403Disabled = new NahookAPIError(403, 'token_disabled', 'Token disabled');
        $this->assertTrue($error403Disabled->isAuthError());
    }

    public function testApiExceptionIsNotFound(): void
    {
        $error = new NahookAPIError(404, 'not_found', 'Not found');
        $this->assertTrue($error->isNotFound());
    }

    public function testApiExceptionIsRateLimited(): void
    {
        $error = new NahookAPIError(429, 'rate_limited', 'Too many requests');
        $this->assertTrue($error->isRateLimited());
    }

    // ── Network Error ──

    public function testNetworkErrorChainsException(): void
    {
        $cause = new \RuntimeException('Connection refused');
        $error = new NahookNetworkError($cause);

        $this->assertSame($cause, $error->getPrevious());
    }

    // ── Timeout Error ──

    public function testTimeoutErrorHasTimeoutMs(): void
    {
        $error = new NahookTimeoutError(15000);

        $this->assertSame(15000, $error->timeoutMs);
        $this->assertSame('Request timed out after 15000ms', $error->getMessage());
    }
}
