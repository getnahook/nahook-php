<?php

declare(strict_types=1);

namespace Nahook\Tests\Integration;

use Nahook\NahookClient;
use Nahook\Errors\NahookAPIError;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests hitting a real Nahook API instance.
 *
 * Required env vars:
 *   NAHOOK_TEST_API_URL, NAHOOK_TEST_API_KEY, NAHOOK_TEST_DISABLED_API_KEY,
 *   NAHOOK_TEST_ENDPOINT_ID, NAHOOK_TEST_EVENT_TYPE
 *
 * @group integration
 */
final class ClientIntegrationTest extends TestCase
{
    private NahookClient $client;
    private string $endpointId;
    private string $eventType;
    private string $apiUrl;
    private string $apiKey;
    private string $disabledApiKey;

    protected function setUp(): void
    {
        $this->apiUrl = getenv('NAHOOK_TEST_API_URL') ?: '';
        $this->apiKey = getenv('NAHOOK_TEST_API_KEY') ?: '';
        $this->disabledApiKey = getenv('NAHOOK_TEST_DISABLED_API_KEY') ?: '';
        $this->endpointId = getenv('NAHOOK_TEST_ENDPOINT_ID') ?: '';
        $this->eventType = getenv('NAHOOK_TEST_EVENT_TYPE') ?: '';

        if (
            $this->apiUrl === '' ||
            $this->apiKey === '' ||
            $this->disabledApiKey === '' ||
            $this->endpointId === '' ||
            $this->eventType === ''
        ) {
            $this->markTestSkipped(
                'Integration test env vars not set (NAHOOK_TEST_API_URL, NAHOOK_TEST_API_KEY, '
                . 'NAHOOK_TEST_DISABLED_API_KEY, NAHOOK_TEST_ENDPOINT_ID, NAHOOK_TEST_EVENT_TYPE)'
            );
        }

        $this->client = new NahookClient($this->apiKey, ['baseUrl' => $this->apiUrl]);
    }

    // ---------------------------------------------------------------
    // send()
    // ---------------------------------------------------------------

    public function testSend_HappyPath(): void
    {
        $result = $this->client->send($this->endpointId, [
            'payload' => ['event' => 'test', 'ts' => time()],
        ]);

        $this->assertSame('accepted', $result['status']);
        $this->assertArrayHasKey('deliveryId', $result);
        $this->assertStringStartsWith('del_', $result['deliveryId']);
        $this->assertArrayHasKey('idempotencyKey', $result);
    }

    public function testSend_IdempotencyDedup(): void
    {
        $key = 'idem-php-' . bin2hex(random_bytes(8));
        $payload = ['payload' => ['dedup' => true], 'idempotencyKey' => $key];

        $first = $this->client->send($this->endpointId, $payload);
        $second = $this->client->send($this->endpointId, $payload);

        $this->assertSame($first['deliveryId'], $second['deliveryId']);
        $this->assertSame($key, $first['idempotencyKey']);
        $this->assertSame($key, $second['idempotencyKey']);
    }

    public function testSend_SeparateKeys(): void
    {
        $keyA = 'idem-php-a-' . bin2hex(random_bytes(8));
        $keyB = 'idem-php-b-' . bin2hex(random_bytes(8));

        $a = $this->client->send($this->endpointId, [
            'payload' => ['sep' => 1],
            'idempotencyKey' => $keyA,
        ]);
        $b = $this->client->send($this->endpointId, [
            'payload' => ['sep' => 2],
            'idempotencyKey' => $keyB,
        ]);

        $this->assertNotSame($a['deliveryId'], $b['deliveryId']);
    }

    // ---------------------------------------------------------------
    // trigger()
    // ---------------------------------------------------------------

    public function testTrigger_FanOut(): void
    {
        $result = $this->client->trigger($this->eventType, [
            'payload' => ['order_id' => 'ord_php_test'],
        ]);

        $this->assertSame('accepted', $result['status']);
        $this->assertArrayHasKey('eventTypeId', $result);
        $this->assertStringStartsWith('evt_', $result['eventTypeId']);
        $this->assertArrayHasKey('deliveryIds', $result);
        $this->assertIsArray($result['deliveryIds']);
        $this->assertGreaterThanOrEqual(1, count($result['deliveryIds']));
    }

    public function testTrigger_Unsubscribed(): void
    {
        $result = $this->client->trigger('event.type.nobody.subscribes.to.this', [
            'payload' => ['noop' => true],
        ]);

        $this->assertSame('accepted', $result['status']);
        $this->assertArrayHasKey('deliveryIds', $result);
        $this->assertIsArray($result['deliveryIds']);
        $this->assertCount(0, $result['deliveryIds']);
    }

    // ---------------------------------------------------------------
    // sendBatch()
    // ---------------------------------------------------------------

    public function testSendBatch_Accepted(): void
    {
        $result = $this->client->sendBatch([
            [
                'endpointId' => $this->endpointId,
                'payload' => ['batch' => 1],
            ],
            [
                'endpointId' => $this->endpointId,
                'payload' => ['batch' => 2],
            ],
        ]);

        $this->assertArrayHasKey('items', $result);
        $this->assertCount(2, $result['items']);

        foreach ($result['items'] as $item) {
            $this->assertSame('accepted', $item['status']);
            $this->assertArrayHasKey('deliveryId', $item);
            $this->assertStringStartsWith('del_', $item['deliveryId']);
        }
    }

    // ---------------------------------------------------------------
    // triggerBatch()
    // ---------------------------------------------------------------

    public function testTriggerBatch_Accepted(): void
    {
        $result = $this->client->triggerBatch([
            [
                'eventType' => $this->eventType,
                'payload' => ['tbatch' => 1],
            ],
            [
                'eventType' => $this->eventType,
                'payload' => ['tbatch' => 2],
            ],
        ]);

        $this->assertArrayHasKey('items', $result);
        $this->assertCount(2, $result['items']);

        foreach ($result['items'] as $item) {
            $this->assertSame('accepted', $item['status']);
            $this->assertArrayHasKey('eventTypeId', $item);
            $this->assertStringStartsWith('evt_', $item['eventTypeId']);
        }
    }

    // ---------------------------------------------------------------
    // Error paths
    // ---------------------------------------------------------------

    public function testError_401_InvalidKey(): void
    {
        $badClient = new NahookClient('nhk_us_boguskey_0000000000000000', [
            'baseUrl' => $this->apiUrl,
            'retries' => 0,
        ]);

        try {
            $badClient->send($this->endpointId, [
                'payload' => ['should' => 'fail'],
            ]);
            $this->fail('Expected NahookAPIError was not thrown');
        } catch (NahookAPIError $e) {
            $this->assertSame(401, $e->status);
            $this->assertTrue($e->isAuthError());
            $this->assertFalse($e->isRetryable());
        }
    }

    public function testError_403_DisabledKey(): void
    {
        $disabledClient = new NahookClient($this->disabledApiKey, [
            'baseUrl' => $this->apiUrl,
            'retries' => 0,
        ]);

        try {
            $disabledClient->send($this->endpointId, [
                'payload' => ['should' => 'fail'],
            ]);
            $this->fail('Expected NahookAPIError was not thrown');
        } catch (NahookAPIError $e) {
            $this->assertSame(403, $e->status);
            $this->assertSame('token_disabled', $e->errorCode);
            $this->assertTrue($e->isAuthError());
        }
    }

    public function testError_404_MissingEndpoint(): void
    {
        try {
            $this->client->send('ep_nonexistent_endpoint_xyz', [
                'payload' => ['should' => 'fail'],
            ]);
            $this->fail('Expected NahookAPIError was not thrown');
        } catch (NahookAPIError $e) {
            $this->assertSame(404, $e->status);
            $this->assertTrue($e->isNotFound());
            $this->assertFalse($e->isRetryable());
        }
    }

    public function testError_400_InvalidEventType(): void
    {
        try {
            $this->client->trigger('!!!invalid-event-type!!!', [
                'payload' => ['should' => 'fail'],
            ]);
            $this->fail('Expected NahookAPIError was not thrown');
        } catch (NahookAPIError $e) {
            $this->assertSame(400, $e->status);
            $this->assertTrue($e->isValidationError());
            $this->assertFalse($e->isRetryable());
        }
    }
}
