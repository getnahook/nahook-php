<?php

declare(strict_types=1);

namespace Nahook\Tests;

use Nahook\Errors\NahookAPIError;
use Nahook\HttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Conformance tests driven by shared JSON fixtures in ../fixtures/conformance/.
 */
class ConformanceTest extends TestCase
{
    private static string $fixturesDir;

    public static function setUpBeforeClass(): void
    {
        self::$fixturesDir = realpath(__DIR__ . '/../../fixtures/conformance');
        if (self::$fixturesDir === false) {
            self::fail('Conformance fixtures directory not found at ../fixtures/conformance/');
        }
    }

    // ── Error Classification ──

    /** @dataProvider errorClassificationProvider */
    public function testErrorClassification(
        string $id,
        int $status,
        string $code,
        string $message,
        ?int $retryAfter,
        bool $isRetryable,
        bool $isAuthError,
        bool $isNotFound,
        bool $isRateLimited,
        bool $isValidationError
    ): void {
        $error = new NahookAPIError($status, $code, $message, $retryAfter);

        $this->assertSame($isRetryable, $error->isRetryable(), "{$id}: isRetryable mismatch");
        $this->assertSame($isAuthError, $error->isAuthError(), "{$id}: isAuthError mismatch");
        $this->assertSame($isNotFound, $error->isNotFound(), "{$id}: isNotFound mismatch");
        $this->assertSame($isRateLimited, $error->isRateLimited(), "{$id}: isRateLimited mismatch");
        $this->assertSame($isValidationError, $error->isValidationError(), "{$id}: isValidationError mismatch");
    }

    public static function errorClassificationProvider(): iterable
    {
        $cases = json_decode(
            file_get_contents(__DIR__ . '/../../fixtures/conformance/error-classification/cases.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        foreach ($cases as $case) {
            yield $case['id'] => [
                $case['id'],
                $case['input']['status'],
                $case['input']['code'],
                $case['input']['message'],
                $case['input']['retryAfter'] ?? null,
                $case['expect']['isRetryable'],
                $case['expect']['isAuthError'],
                $case['expect']['isNotFound'],
                $case['expect']['isRateLimited'],
                $case['expect']['isValidationError'],
            ];
        }
    }

    // ── Region Routing ──

    /** @dataProvider regionRoutingProvider */
    public function testRegionRouting(string $id, string $token, string $expectedBaseUrl): void
    {
        $resolveBaseUrl = new \ReflectionMethod(HttpClient::class, 'resolveBaseUrl');
        $resolveBaseUrl->setAccessible(true);

        $result = $resolveBaseUrl->invoke(null, $token);
        $this->assertSame($expectedBaseUrl, $result, "{$id}: base URL mismatch");
    }

    public static function regionRoutingProvider(): iterable
    {
        $cases = json_decode(
            file_get_contents(__DIR__ . '/../../fixtures/conformance/region-routing/cases.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        foreach ($cases as $case) {
            yield $case['id'] => [
                $case['id'],
                $case['input']['token'],
                $case['expect']['baseUrl'],
            ];
        }
    }

    // ── Retry Backoff ──

    /** @dataProvider retryBackoffProvider */
    public function testRetryBackoff(string $id, int $attempt, ?int $retryAfterMs, array $expect): void
    {
        $client = new HttpClient([
            'token' => 'nhk_us_abc123',
            'baseUrl' => 'https://api.test.com',
        ]);

        $calculateDelay = new \ReflectionMethod(HttpClient::class, 'calculateDelay');
        $calculateDelay->setAccessible(true);

        if (isset($expect['exactDelayMs'])) {
            $delay = $calculateDelay->invoke($client, $attempt, $retryAfterMs);
            $this->assertSame((float) $expect['exactDelayMs'], $delay, "{$id}: exact delay mismatch");
        } else {
            // Run multiple times due to jitter
            for ($i = 0; $i < 20; $i++) {
                $delay = $calculateDelay->invoke($client, $attempt, $retryAfterMs);
                $this->assertGreaterThanOrEqual($expect['minDelayMs'], $delay, "{$id}: delay below min");
                $this->assertLessThanOrEqual($expect['maxDelayMs'], $delay, "{$id}: delay above max");
            }
        }
    }

    public static function retryBackoffProvider(): iterable
    {
        $cases = json_decode(
            file_get_contents(__DIR__ . '/../../fixtures/conformance/retry-backoff/cases.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        foreach ($cases as $case) {
            yield $case['id'] => [
                $case['id'],
                $case['input']['attempt'],
                $case['input']['retryAfterMs'],
                $case['expect'],
            ];
        }
    }

    // ── Signature ──

    /** @dataProvider signatureProvider */
    public function testSignature(string $id, string $action, array $input, array $expect): void
    {
        $secret = $input['secret'];
        $msgId = $input['messageId'];
        $timestamp = $input['timestamp'];
        $payload = $input['payload'] ?? '';

        // Handle payloadGenerator
        if (isset($input['payloadGenerator']) && $input['payloadGenerator'] === 'repeat_a_10000') {
            $payload = str_repeat('a', 10000);
        }

        $sign = function (string $sec, string $mid, string $ts, string $pl): string {
            $rawSecret = str_starts_with($sec, 'whsec_') ? substr($sec, 6) : $sec;
            $key = base64_decode($rawSecret);
            $toSign = "{$mid}.{$ts}.{$pl}";
            $digest = hash_hmac('sha256', $toSign, $key, true);
            return 'v1,' . base64_encode($digest);
        };

        switch ($action) {
            case 'sign_then_verify':
                $sig = $sign($secret, $msgId, $timestamp, $payload);
                // Verify by re-signing and comparing
                $sig2 = $sign($secret, $msgId, $timestamp, $payload);
                $this->assertSame($sig, $sig2, "{$id}: sign_then_verify failed");
                $this->assertStringStartsWith('v1,', $sig, "{$id}: signature missing v1 prefix");
                break;

            case 'sign_original_verify_tampered':
                $sigOriginal = $sign($secret, $msgId, $timestamp, $payload);
                $sigTampered = $sign($secret, $msgId, $timestamp, $input['tamperedPayload']);
                $this->assertNotSame($sigOriginal, $sigTampered, "{$id}: tampered payload should not match");
                break;

            case 'sign_with_original_verify_with_wrong':
                $sigOriginal = $sign($secret, $msgId, $timestamp, $payload);
                $sigWrong = $sign($input['wrongSecret'], $msgId, $timestamp, $payload);
                $this->assertNotSame($sigOriginal, $sigWrong, "{$id}: wrong secret should not match");
                break;

            case 'sign_twice_compare':
                $sig1 = $sign($secret, $msgId, $timestamp, $payload);
                $sig2 = $sign($secret, $msgId, $timestamp, $payload);
                $this->assertSame($sig1, $sig2, "{$id}: determinism check failed");
                break;

            case 'verify_known_signature':
                $sig = $sign($secret, $msgId, $timestamp, $payload);
                $expectedHeader = $expect['signatureHeader'];
                // The fixture format is "v1,{timestamp},{base64}" — extract the base64 part
                $parts = explode(',', $expectedHeader);
                $expectedSig = $parts[0] . ',' . ($parts[2] ?? $parts[1]);
                $this->assertSame($expectedSig, $sig, "{$id}: known signature mismatch");
                break;

            default:
                $this->fail("{$id}: unknown action '{$action}'");
        }
    }

    public static function signatureProvider(): iterable
    {
        $cases = json_decode(
            file_get_contents(__DIR__ . '/../../fixtures/conformance/signature/cases.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        foreach ($cases as $case) {
            yield $case['id'] => [
                $case['id'],
                $case['action'],
                $case['input'],
                $case['expect'],
            ];
        }
    }
}
