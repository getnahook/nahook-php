<?php

declare(strict_types=1);

namespace Nahook\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Nahook\Errors\NahookAPIError;
use Nahook\NahookManagement;
use PHPUnit\Framework\TestCase;

/**
 * Negative / resilience tests (NEG-01 through NEG-06).
 *
 * Uses Guzzle MockHandler to simulate abnormal server responses.
 */
class NegativeTest extends TestCase
{
    private const TOKEN = 'nhm_test123';
    private const BASE_URL = 'https://api.test.com';

    /** @var array<array{request: \Psr\Http\Message\RequestInterface}> */
    private array $history = [];

    private function createManagement(MockHandler $mock): NahookManagement
    {
        $this->history = [];
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($this->history));

        return new NahookManagement(self::TOKEN, [
            'baseUrl' => self::BASE_URL,
            'handler' => $handlerStack,
        ]);
    }

    // NEG-01: Malformed JSON response on 200
    public function testNeg01MalformedJsonResponseOn200(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{invalid json!!!'),
        ]);
        $mgmt = $this->createManagement($mock);

        // json_decode returns null for malformed JSON, SDK should handle gracefully
        // The SDK wraps result in ['data' => ...], so this should not throw but return null data
        $result = $mgmt->endpoints->list('ws_abc');
        $this->assertNull($result['data']);
    }

    // NEG-02: Empty body on 200
    public function testNeg02EmptyBodyOn200(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], ''),
        ]);
        $mgmt = $this->createManagement($mock);

        $result = $mgmt->endpoints->list('ws_abc');
        $this->assertNull($result['data']);
    }

    // NEG-03: 5xx with HTML body
    public function testNeg03ServerErrorWithHtmlBody(): void
    {
        $mock = new MockHandler([
            new Response(503, ['Content-Type' => 'text/html'], '<html><body>Service Unavailable</body></html>'),
        ]);
        $mgmt = $this->createManagement($mock);

        $this->expectException(NahookAPIError::class);
        try {
            $mgmt->endpoints->list('ws_abc');
        } catch (NahookAPIError $e) {
            $this->assertSame(503, $e->status);
            $this->assertTrue($e->isRetryable());
            throw $e;
        }
    }

    // NEG-04: 5xx with completely empty body
    public function testNeg04ServerErrorWithEmptyBody(): void
    {
        $mock = new MockHandler([
            new Response(500, ['Content-Type' => 'application/json'], ''),
        ]);
        $mgmt = $this->createManagement($mock);

        $this->expectException(NahookAPIError::class);
        try {
            $mgmt->endpoints->list('ws_abc');
        } catch (NahookAPIError $e) {
            $this->assertSame(500, $e->status);
            $this->assertTrue($e->isRetryable());
            throw $e;
        }
    }

    // NEG-05: Response with unknown extra fields is handled gracefully
    public function testNeg05UnknownExtraFieldsHandledGracefully(): void
    {
        $body = json_encode([
            ['id' => 'ep_1', 'url' => 'https://example.com', 'unknownField' => 'should_be_ignored', 'nested' => ['deep' => true]],
        ]);
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $body),
        ]);
        $mgmt = $this->createManagement($mock);

        $result = $mgmt->endpoints->list('ws_abc');
        $this->assertNotNull($result['data']);
        $this->assertCount(1, $result['data']);
        $this->assertSame('ep_1', $result['data'][0]['id']);
    }

    // NEG-06: Response missing optional fields defaults gracefully
    public function testNeg06MissingOptionalFieldsHandledGracefully(): void
    {
        $body = json_encode([['id' => 'ep_1']]);
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], $body),
        ]);
        $mgmt = $this->createManagement($mock);

        $result = $mgmt->endpoints->list('ws_abc');
        $this->assertNotNull($result['data']);
        $this->assertCount(1, $result['data']);
        $this->assertSame('ep_1', $result['data'][0]['id']);
    }
}
