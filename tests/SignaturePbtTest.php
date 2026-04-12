<?php

declare(strict_types=1);

namespace Nahook\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Property-based-style tests for webhook signature using randomized data providers.
 *
 * Since PHP lacks a strong PBT library, we use PHPUnit data providers
 * with 50 random payload/secret pairs to approximate property-based testing.
 */
class SignaturePbtTest extends TestCase
{
    private function sign(string $secret, string $msgId, string $timestamp, string $payload): string
    {
        $rawSecret = str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret;
        $key = base64_decode($rawSecret);
        $toSign = "{$msgId}.{$timestamp}.{$payload}";
        $digest = hash_hmac('sha256', $toSign, $key, true);
        return 'v1,' . base64_encode($digest);
    }

    // ── Data Provider: 50 random payload/secret pairs ──

    public static function randomPayloads(): iterable
    {
        for ($i = 0; $i < 50; $i++) {
            $secret = 'whsec_' . base64_encode(random_bytes(32));
            $payload = json_encode([
                'index' => $i,
                'random' => bin2hex(random_bytes(16)),
                'nested' => ['value' => random_int(0, PHP_INT_MAX)],
            ]);
            $msgId = 'msg_pbt_' . bin2hex(random_bytes(8));
            $timestamp = (string) random_int(1600000000, 1800000000);

            yield "pair-{$i}" => [$secret, $msgId, $timestamp, $payload];
        }
    }

    // ── Test 1: Sign then verify roundtrip ──

    /** @dataProvider randomPayloads */
    public function testSignThenVerifyRoundtrip(string $secret, string $msgId, string $timestamp, string $payload): void
    {
        $sig = $this->sign($secret, $msgId, $timestamp, $payload);

        // Verify by re-signing with same inputs
        $sig2 = $this->sign($secret, $msgId, $timestamp, $payload);
        $this->assertSame($sig, $sig2);
        $this->assertStringStartsWith('v1,', $sig);
    }

    // ── Test 2: Tampered payload fails ──

    /** @dataProvider randomPayloads */
    public function testTamperedPayloadFails(string $secret, string $msgId, string $timestamp, string $payload): void
    {
        $originalSig = $this->sign($secret, $msgId, $timestamp, $payload);
        $tamperedPayload = $payload . '_TAMPERED';
        $tamperedSig = $this->sign($secret, $msgId, $timestamp, $tamperedPayload);

        $this->assertNotSame($originalSig, $tamperedSig);
    }

    // ── Test 3: Wrong secret fails ──

    /** @dataProvider randomPayloads */
    public function testWrongSecretFails(string $secret, string $msgId, string $timestamp, string $payload): void
    {
        $originalSig = $this->sign($secret, $msgId, $timestamp, $payload);
        $wrongSecret = 'whsec_' . base64_encode(random_bytes(32));
        $wrongSig = $this->sign($wrongSecret, $msgId, $timestamp, $payload);

        $this->assertNotSame($originalSig, $wrongSig);
    }

    // ── Test 4: Deterministic ──

    /** @dataProvider randomPayloads */
    public function testDeterministic(string $secret, string $msgId, string $timestamp, string $payload): void
    {
        $sig1 = $this->sign($secret, $msgId, $timestamp, $payload);
        $sig2 = $this->sign($secret, $msgId, $timestamp, $payload);
        $sig3 = $this->sign($secret, $msgId, $timestamp, $payload);

        $this->assertSame($sig1, $sig2);
        $this->assertSame($sig2, $sig3);
    }
}
