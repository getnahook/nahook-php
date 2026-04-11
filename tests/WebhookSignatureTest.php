<?php

declare(strict_types=1);

namespace Nahook\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Webhook signature verification tests.
 *
 * Validates that the Standard Webhooks signing format used by the Nahook API
 * can be correctly produced and verified using native crypto.
 *
 * Signing spec:
 *   base   = "{msgId}.{timestamp}.{payload}"
 *   key    = base64_decode(secret_without_whsec_prefix)
 *   sig    = "v1," + base64(HMAC-SHA256(key, base))
 *   headers: webhook-id, webhook-timestamp, webhook-signature
 */
class WebhookSignatureTest extends TestCase
{
    private const TEST_SECRET = 'whsec_dGVzdF93ZWJob29rX3NpZ25pbmdfa2V5XzMyYnl0ZXMh';
    private const MSG_ID = 'msg_test_sig_001';
    private const TIMESTAMP = '1712345678';
    private const PAYLOAD = '{"order_id":"ord_123","amount":49.99}';

    private function computeSignature(string $secret, string $msgId, string $timestamp, string $payload): string
    {
        $rawSecret = str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret;
        $key = base64_decode($rawSecret);

        $toSign = "{$msgId}.{$timestamp}.{$payload}";
        $digest = hash_hmac('sha256', $toSign, $key, true);

        return 'v1,' . base64_encode($digest);
    }

    public function testProducesValidV1Signature(): void
    {
        $sig = $this->computeSignature(self::TEST_SECRET, self::MSG_ID, self::TIMESTAMP, self::PAYLOAD);
        $this->assertMatchesRegularExpression('/^v1,[A-Za-z0-9+\/]+=*$/', $sig);
    }

    public function testDeterministicSameInputsSameSignature(): void
    {
        $sig1 = $this->computeSignature(self::TEST_SECRET, self::MSG_ID, self::TIMESTAMP, self::PAYLOAD);
        $sig2 = $this->computeSignature(self::TEST_SECRET, self::MSG_ID, self::TIMESTAMP, self::PAYLOAD);
        $this->assertSame($sig1, $sig2);
    }

    public function testRejectsTamperedPayload(): void
    {
        $original = $this->computeSignature(self::TEST_SECRET, self::MSG_ID, self::TIMESTAMP, self::PAYLOAD);
        $tampered = $this->computeSignature(self::TEST_SECRET, self::MSG_ID, self::TIMESTAMP,
            '{"order_id":"ord_123","amount":99.99}');
        $this->assertNotSame($original, $tampered);
    }

    public function testRejectsWrongSecret(): void
    {
        $original = $this->computeSignature(self::TEST_SECRET, self::MSG_ID, self::TIMESTAMP, self::PAYLOAD);
        $wrong = $this->computeSignature('whsec_d3Jvbmdfc2VjcmV0', self::MSG_ID, self::TIMESTAMP, self::PAYLOAD);
        $this->assertNotSame($original, $wrong);
    }

    public function testRejectsTamperedMsgId(): void
    {
        $original = $this->computeSignature(self::TEST_SECRET, self::MSG_ID, self::TIMESTAMP, self::PAYLOAD);
        $tampered = $this->computeSignature(self::TEST_SECRET, 'msg_tampered_id', self::TIMESTAMP, self::PAYLOAD);
        $this->assertNotSame($original, $tampered);
    }

    public function testRejectsTamperedTimestamp(): void
    {
        $original = $this->computeSignature(self::TEST_SECRET, self::MSG_ID, self::TIMESTAMP, self::PAYLOAD);
        $tampered = $this->computeSignature(self::TEST_SECRET, self::MSG_ID, '9999999999', self::PAYLOAD);
        $this->assertNotSame($original, $tampered);
    }

    public function testCorrectHeadersStructure(): void
    {
        $sig = $this->computeSignature(self::TEST_SECRET, self::MSG_ID, self::TIMESTAMP, self::PAYLOAD);
        $headers = [
            'content-type' => 'application/json',
            'webhook-id' => self::MSG_ID,
            'webhook-timestamp' => self::TIMESTAMP,
            'webhook-signature' => $sig,
        ];

        $this->assertStringStartsWith('msg_', $headers['webhook-id']);
        $this->assertStringStartsWith('v1,', $headers['webhook-signature']);
        $this->assertMatchesRegularExpression('/^\d+$/', $headers['webhook-timestamp']);
        $this->assertSame('application/json', $headers['content-type']);
    }

    public function testHandlesSecretWithoutPrefix(): void
    {
        $rawSecret = substr(self::TEST_SECRET, 6);
        $withPrefix = $this->computeSignature(self::TEST_SECRET, self::MSG_ID, self::TIMESTAMP, self::PAYLOAD);
        $withoutPrefix = $this->computeSignature($rawSecret, self::MSG_ID, self::TIMESTAMP, self::PAYLOAD);
        $this->assertSame($withPrefix, $withoutPrefix);
    }

    public function testMatchesKnownCrossLanguageReferenceSignature(): void
    {
        $sig = $this->computeSignature(self::TEST_SECRET, self::MSG_ID, self::TIMESTAMP, self::PAYLOAD);
        $this->assertSame('v1,VF1JBS4kdSwmE64FeeiWTgszlPCfaop53x8bwzvHizw=', $sig);
    }

    public function testEmptyPayloadProducesValidSignature(): void
    {
        $sig = $this->computeSignature(self::TEST_SECRET, self::MSG_ID, self::TIMESTAMP, '');
        $this->assertSame('v1,yNFeVvBSs4aZ/sVHHw1MaUWnN1IGK/Ul/16T8aptSJo=', $sig);
    }

    public function testUnicodePayloadConsistentAcrossLanguages(): void
    {
        $sig = $this->computeSignature(self::TEST_SECRET, self::MSG_ID, self::TIMESTAMP, '{"name":"café","price":"€9.99"}');
        $this->assertSame('v1,GcuGAMV9tELnF2rjay6sA8uo5PDPPlhaFi6gKUg06wQ=', $sig);
    }
}
