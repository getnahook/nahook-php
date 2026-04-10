<?php

declare(strict_types=1);

namespace Nahook\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Nahook\NahookClient;
use Nahook\NahookManagement;
use PHPUnit\Framework\TestCase;

class ManagementTest extends TestCase
{
    private const TOKEN = 'nhm_test123';
    private const CLIENT_KEY = 'nhk_test123';
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

    private function createClient(MockHandler $mock): NahookClient
    {
        $this->history = [];
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($this->history));

        return new NahookClient(self::CLIENT_KEY, [
            'baseUrl' => self::BASE_URL,
            'handler' => $handlerStack,
        ]);
    }

    private function lastRequest(): \Psr\Http\Message\RequestInterface
    {
        return $this->history[count($this->history) - 1]['request'];
    }

    // ── Token Validation ──

    public function testRejectsInvalidManagementTokenPrefix(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("must start with 'nhm_'");
        new NahookManagement('bad_token');
    }

    public function testAcceptsValidManagementToken(): void
    {
        $mock = new MockHandler([]);
        $mgmt = $this->createManagement($mock);
        $this->assertNotNull($mgmt->endpoints);
        $this->assertNotNull($mgmt->eventTypes);
        $this->assertNotNull($mgmt->applications);
        $this->assertNotNull($mgmt->subscriptions);
        $this->assertNotNull($mgmt->portalSessions);
    }

    public function testRejectsInvalidClientKeyPrefix(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("must start with 'nhk_'");
        new NahookClient('bad_key');
    }

    public function testAcceptsValidClientKey(): void
    {
        $mock = new MockHandler([]);
        $client = $this->createClient($mock);
        $this->assertNotNull($client);
    }

    // ── Endpoints ──

    public function testEndpointsListSendsGetToEndpoints(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([['id' => 'ep_1', 'url' => 'https://example.com']])),
        ]);
        $mgmt = $this->createManagement($mock);

        $result = $mgmt->endpoints->list('ws_abc');
        $request = $this->lastRequest();

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame(
            'https://api.test.com/management/v1/workspaces/ws_abc/endpoints',
            (string) $request->getUri()
        );
        $this->assertCount(1, $result['data']);
    }

    public function testEndpointsCreateSendsPostWithBody(): void
    {
        $mock = new MockHandler([
            new Response(201, [], json_encode(['id' => 'ep_new', 'url' => 'https://example.com'])),
        ]);
        $mgmt = $this->createManagement($mock);

        $mgmt->endpoints->create('ws_abc', ['url' => 'https://example.com', 'description' => 'Test']);
        $request = $this->lastRequest();

        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('/endpoints', (string) $request->getUri());
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('https://example.com', $body['url']);
        $this->assertSame('Test', $body['description']);
    }

    public function testEndpointsGetSendsGetToEndpointsId(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['id' => 'ep_1'])),
        ]);
        $mgmt = $this->createManagement($mock);

        $mgmt->endpoints->get('ws_abc', 'ep_1');
        $request = $this->lastRequest();

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame(
            'https://api.test.com/management/v1/workspaces/ws_abc/endpoints/ep_1',
            (string) $request->getUri()
        );
    }

    public function testEndpointsUpdateSendsPatchWithBody(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['id' => 'ep_1', 'description' => 'Updated'])),
        ]);
        $mgmt = $this->createManagement($mock);

        $mgmt->endpoints->update('ws_abc', 'ep_1', ['description' => 'Updated']);
        $request = $this->lastRequest();

        $this->assertSame('PATCH', $request->getMethod());
        $this->assertStringContainsString('/endpoints/ep_1', (string) $request->getUri());
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('Updated', $body['description']);
    }

    public function testEndpointsDeleteSendsDelete(): void
    {
        $mock = new MockHandler([
            new Response(204),
        ]);
        $mgmt = $this->createManagement($mock);

        $mgmt->endpoints->delete('ws_abc', 'ep_1');
        $request = $this->lastRequest();

        $this->assertSame('DELETE', $request->getMethod());
        $this->assertStringContainsString('/endpoints/ep_1', (string) $request->getUri());
    }

    // ── Event Types ──

    public function testEventTypesListSendsGetToEventTypes(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([['id' => 'evt_1', 'name' => 'order.paid']])),
        ]);
        $mgmt = $this->createManagement($mock);

        $result = $mgmt->eventTypes->list('ws_abc');
        $request = $this->lastRequest();

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame(
            'https://api.test.com/management/v1/workspaces/ws_abc/event-types',
            (string) $request->getUri()
        );
        $this->assertCount(1, $result['data']);
    }

    public function testEventTypesCreateSendsPostWithBody(): void
    {
        $mock = new MockHandler([
            new Response(201, [], json_encode(['id' => 'evt_new', 'name' => 'order.paid'])),
        ]);
        $mgmt = $this->createManagement($mock);

        $mgmt->eventTypes->create('ws_abc', ['name' => 'order.paid', 'description' => 'Paid']);
        $request = $this->lastRequest();

        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('/event-types', (string) $request->getUri());
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('order.paid', $body['name']);
    }

    public function testEventTypesGetSendsGetToEventTypesId(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['id' => 'evt_1'])),
        ]);
        $mgmt = $this->createManagement($mock);

        $mgmt->eventTypes->get('ws_abc', 'evt_1');
        $request = $this->lastRequest();

        $this->assertSame('GET', $request->getMethod());
        $this->assertStringContainsString('/event-types/evt_1', (string) $request->getUri());
    }

    public function testEventTypesUpdateSendsPatchWithBody(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['id' => 'evt_1'])),
        ]);
        $mgmt = $this->createManagement($mock);

        $mgmt->eventTypes->update('ws_abc', 'evt_1', ['description' => 'Updated']);
        $request = $this->lastRequest();

        $this->assertSame('PATCH', $request->getMethod());
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('Updated', $body['description']);
    }

    public function testEventTypesDeleteSendsDelete(): void
    {
        $mock = new MockHandler([
            new Response(204),
        ]);
        $mgmt = $this->createManagement($mock);

        $mgmt->eventTypes->delete('ws_abc', 'evt_1');
        $request = $this->lastRequest();

        $this->assertSame('DELETE', $request->getMethod());
    }

    // ── Applications ──

    public function testApplicationsListSendsGetWithQueryParams(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([['id' => 'app_1', 'name' => 'Acme']])),
        ]);
        $mgmt = $this->createManagement($mock);

        $result = $mgmt->applications->list('ws_abc', ['limit' => 10, 'offset' => 20]);
        $request = $this->lastRequest();

        $this->assertSame('GET', $request->getMethod());
        $uri = (string) $request->getUri();
        $this->assertStringContainsString('/applications', $uri);
        $this->assertStringContainsString('limit=10', $uri);
        $this->assertStringContainsString('offset=20', $uri);
        $this->assertCount(1, $result['data']);
    }

    public function testApplicationsListOmitsUndefinedQueryParams(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([])),
        ]);
        $mgmt = $this->createManagement($mock);

        $mgmt->applications->list('ws_abc');
        $request = $this->lastRequest();

        $this->assertSame(
            'https://api.test.com/management/v1/workspaces/ws_abc/applications',
            (string) $request->getUri()
        );
    }

    public function testApplicationsCreateSendsPostWithBody(): void
    {
        $mock = new MockHandler([
            new Response(201, [], json_encode(['id' => 'app_new', 'name' => 'Acme'])),
        ]);
        $mgmt = $this->createManagement($mock);

        $mgmt->applications->create('ws_abc', ['name' => 'Acme', 'externalId' => 'ext-1']);
        $request = $this->lastRequest();

        $this->assertSame('POST', $request->getMethod());
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('Acme', $body['name']);
        $this->assertSame('ext-1', $body['externalId']);
    }

    public function testApplicationsGetSendsGetToApplicationsId(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['id' => 'app_1'])),
        ]);
        $mgmt = $this->createManagement($mock);

        $mgmt->applications->get('ws_abc', 'app_1');
        $request = $this->lastRequest();

        $this->assertSame('GET', $request->getMethod());
        $this->assertStringContainsString('/applications/app_1', (string) $request->getUri());
    }

    public function testApplicationsUpdateSendsPatch(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['id' => 'app_1'])),
        ]);
        $mgmt = $this->createManagement($mock);

        $mgmt->applications->update('ws_abc', 'app_1', ['name' => 'Updated']);
        $request = $this->lastRequest();

        $this->assertSame('PATCH', $request->getMethod());
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('Updated', $body['name']);
    }

    public function testApplicationsDeleteSendsDelete(): void
    {
        $mock = new MockHandler([
            new Response(204),
        ]);
        $mgmt = $this->createManagement($mock);

        $mgmt->applications->delete('ws_abc', 'app_1');
        $request = $this->lastRequest();

        $this->assertSame('DELETE', $request->getMethod());
    }

    public function testApplicationsListEndpointsSendsGetToApplicationsIdEndpoints(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([['id' => 'ep_1']])),
        ]);
        $mgmt = $this->createManagement($mock);

        $result = $mgmt->applications->listEndpoints('ws_abc', 'app_1');
        $request = $this->lastRequest();

        $this->assertSame('GET', $request->getMethod());
        $this->assertStringContainsString('/applications/app_1/endpoints', (string) $request->getUri());
        $this->assertCount(1, $result['data']);
    }

    public function testApplicationsCreateEndpointSendsPostToApplicationsIdEndpoints(): void
    {
        $mock = new MockHandler([
            new Response(201, [], json_encode(['id' => 'ep_new'])),
        ]);
        $mgmt = $this->createManagement($mock);

        $mgmt->applications->createEndpoint('ws_abc', 'app_1', ['url' => 'https://example.com']);
        $request = $this->lastRequest();

        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('/applications/app_1/endpoints', (string) $request->getUri());
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('https://example.com', $body['url']);
    }

    // ── Subscriptions ──

    public function testSubscriptionsListSendsGetToEndpointsIdSubscriptions(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([['id' => 'sub_1']])),
        ]);
        $mgmt = $this->createManagement($mock);

        $result = $mgmt->subscriptions->list('ws_abc', 'ep_1');
        $request = $this->lastRequest();

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame(
            'https://api.test.com/management/v1/workspaces/ws_abc/endpoints/ep_1/subscriptions',
            (string) $request->getUri()
        );
        $this->assertCount(1, $result['data']);
    }

    public function testSubscriptionsCreateSendsPostWithEventTypeId(): void
    {
        $mock = new MockHandler([
            new Response(201, [], json_encode(['id' => 'sub_new'])),
        ]);
        $mgmt = $this->createManagement($mock);

        $mgmt->subscriptions->create('ws_abc', 'ep_1', ['eventTypeId' => 'evt_1']);
        $request = $this->lastRequest();

        $this->assertSame('POST', $request->getMethod());
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('evt_1', $body['eventTypeId']);
    }

    public function testSubscriptionsDeleteSendsDeleteWithEventTypeIdInPath(): void
    {
        $mock = new MockHandler([
            new Response(204),
        ]);
        $mgmt = $this->createManagement($mock);

        $mgmt->subscriptions->delete('ws_abc', 'ep_1', 'evt_1');
        $request = $this->lastRequest();

        $this->assertSame('DELETE', $request->getMethod());
        $this->assertStringContainsString('/subscriptions/evt_1', (string) $request->getUri());
    }

    // ── Portal Sessions ──

    public function testPortalSessionsCreateSendsPostToApplicationsIdPortal(): void
    {
        $mock = new MockHandler([
            new Response(201, [], json_encode([
                'url' => 'https://portal.nahook.com/s/abc',
                'code' => 'xyz',
                'expiresAt' => '2026-04-10T12:00:00Z',
            ])),
        ]);
        $mgmt = $this->createManagement($mock);

        $result = $mgmt->portalSessions->create('ws_abc', 'app_1', ['metadata' => ['userId' => 'u-1']]);
        $request = $this->lastRequest();

        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('/applications/app_1/portal', (string) $request->getUri());
        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('u-1', $body['metadata']['userId']);
        $this->assertStringContainsString('portal.nahook.com', $result['url']);
    }

    public function testPortalSessionsCreateSendsEmptyBodyWhenNoOptions(): void
    {
        $mock = new MockHandler([
            new Response(201, [], json_encode([
                'url' => 'https://portal.nahook.com/s/abc',
                'code' => 'xyz',
                'expiresAt' => '2026-04-10T12:00:00Z',
            ])),
        ]);
        $mgmt = $this->createManagement($mock);

        $mgmt->portalSessions->create('ws_abc', 'app_1');
        $request = $this->lastRequest();

        $body = json_decode((string) $request->getBody(), true);
        $this->assertEmpty($body);
    }

    // ── Headers ──

    public function testSendsAuthorizationHeaderWithManagementToken(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([])),
        ]);
        $mgmt = $this->createManagement($mock);

        $mgmt->endpoints->list('ws_abc');
        $request = $this->lastRequest();

        $this->assertSame('Bearer nhm_test123', $request->getHeaderLine('Authorization'));
    }

    public function testSendsUserAgentHeader(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([])),
        ]);
        $mgmt = $this->createManagement($mock);

        $mgmt->endpoints->list('ws_abc');
        $request = $this->lastRequest();

        $this->assertStringStartsWith('nahook-php/', $request->getHeaderLine('User-Agent'));
    }

    public function testOmitsContentTypeOnGetRequests(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([])),
        ]);
        $mgmt = $this->createManagement($mock);

        $mgmt->endpoints->list('ws_abc');
        $request = $this->lastRequest();

        $this->assertFalse($request->hasHeader('Content-Type'));
    }

    public function testIncludesContentTypeOnPostRequests(): void
    {
        $mock = new MockHandler([
            new Response(201, [], json_encode(['id' => 'ep_new'])),
        ]);
        $mgmt = $this->createManagement($mock);

        $mgmt->endpoints->create('ws_abc', ['url' => 'https://example.com']);
        $request = $this->lastRequest();

        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
    }

    // ── Client Methods ──

    public function testClientSendCallsCorrectEndpoint(): void
    {
        $mock = new MockHandler([
            new Response(202, [], json_encode([
                'deliveryId' => 'del_abc',
                'idempotencyKey' => 'key-123',
                'status' => 'accepted',
            ])),
        ]);
        $client = $this->createClient($mock);

        $result = $client->send('ep_123', ['payload' => ['test' => true]]);
        $request = $this->lastRequest();

        $this->assertSame('del_abc', $result['deliveryId']);
        $this->assertSame('accepted', $result['status']);
        $this->assertStringContainsString('/api/ingest/ep_123', (string) $request->getUri());
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('Bearer nhk_test123', $request->getHeaderLine('Authorization'));
    }

    public function testClientSendGeneratesIdempotencyKey(): void
    {
        $mock = new MockHandler([
            new Response(202, [], json_encode([
                'deliveryId' => 'del_abc',
                'idempotencyKey' => 'auto-generated',
                'status' => 'accepted',
            ])),
        ]);
        $client = $this->createClient($mock);

        $client->send('ep_123', ['payload' => ['test' => true]]);
        $request = $this->lastRequest();

        $body = json_decode((string) $request->getBody(), true);
        $this->assertArrayHasKey('idempotencyKey', $body);
        // UUID v4 format
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $body['idempotencyKey']
        );
    }

    public function testClientSendUsesProvidedIdempotencyKey(): void
    {
        $mock = new MockHandler([
            new Response(202, [], json_encode([
                'deliveryId' => 'del_abc',
                'idempotencyKey' => 'my-key',
                'status' => 'accepted',
            ])),
        ]);
        $client = $this->createClient($mock);

        $client->send('ep_123', ['payload' => ['test' => true], 'idempotencyKey' => 'my-key']);
        $request = $this->lastRequest();

        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame('my-key', $body['idempotencyKey']);
    }

    public function testClientTriggerCallsCorrectEndpoint(): void
    {
        $mock = new MockHandler([
            new Response(202, [], json_encode([
                'eventTypeId' => 'evt_abc',
                'deliveryIds' => ['del_1'],
                'status' => 'accepted',
            ])),
        ]);
        $client = $this->createClient($mock);

        $result = $client->trigger('order.paid', ['payload' => ['orderId' => '123']]);
        $request = $this->lastRequest();

        $this->assertSame('evt_abc', $result['eventTypeId']);
        $this->assertStringContainsString('/api/ingest/event/order.paid', (string) $request->getUri());
    }

    public function testClientTriggerIncludesMetadataWhenProvided(): void
    {
        $mock = new MockHandler([
            new Response(202, [], json_encode([
                'eventTypeId' => 'evt_abc',
                'deliveryIds' => [],
                'status' => 'accepted',
            ])),
        ]);
        $client = $this->createClient($mock);

        $client->trigger('order.paid', [
            'payload' => ['orderId' => '123'],
            'metadata' => ['source' => 'test'],
        ]);
        $request = $this->lastRequest();

        $body = json_decode((string) $request->getBody(), true);
        $this->assertSame(['source' => 'test'], $body['metadata']);
    }

    public function testClientTriggerOmitsMetadataWhenNotProvided(): void
    {
        $mock = new MockHandler([
            new Response(202, [], json_encode([
                'eventTypeId' => 'evt_abc',
                'deliveryIds' => [],
                'status' => 'accepted',
            ])),
        ]);
        $client = $this->createClient($mock);

        $client->trigger('order.paid', ['payload' => ['orderId' => '123']]);
        $request = $this->lastRequest();

        $body = json_decode((string) $request->getBody(), true);
        $this->assertArrayNotHasKey('metadata', $body);
    }

    public function testClientSendBatchCallsCorrectEndpoint(): void
    {
        $mock = new MockHandler([
            new Response(202, [], json_encode([
                'items' => [['index' => 0, 'deliveryId' => 'del_abc', 'status' => 'accepted']],
            ])),
        ]);
        $client = $this->createClient($mock);

        $result = $client->sendBatch([['endpointId' => 'ep_123', 'payload' => ['test' => true]]]);
        $request = $this->lastRequest();

        $this->assertCount(1, $result['items']);
        $this->assertSame('accepted', $result['items'][0]['status']);
        $this->assertStringContainsString('/api/ingest/batch', (string) $request->getUri());
    }

    public function testClientTriggerBatchCallsCorrectEndpoint(): void
    {
        $mock = new MockHandler([
            new Response(202, [], json_encode([
                'items' => [['index' => 0, 'eventTypeId' => 'evt_abc', 'deliveryIds' => [], 'status' => 'accepted']],
            ])),
        ]);
        $client = $this->createClient($mock);

        $result = $client->triggerBatch([['eventType' => 'order.paid', 'payload' => ['orderId' => '123']]]);
        $request = $this->lastRequest();

        $this->assertCount(1, $result['items']);
        $this->assertStringContainsString('/api/ingest/event/batch', (string) $request->getUri());
    }

    public function testClientThrowsNahookAPIErrorOnErrorResponse(): void
    {
        $mock = new MockHandler([
            new Response(404, [], json_encode([
                'error' => ['code' => 'not_found', 'message' => 'Endpoint not found'],
            ])),
        ]);
        $client = $this->createClient($mock);

        $this->expectException(\Nahook\Errors\NahookAPIError::class);
        $this->expectExceptionMessage('Endpoint not found');

        $client->send('ep_missing', ['payload' => []]);
    }

    // ── Error Classes ──

    public function testNahookAPIErrorIsRetryableFor5xx(): void
    {
        $error = new \Nahook\Errors\NahookAPIError(500, 'internal', 'Server error');
        $this->assertTrue($error->isRetryable());
    }

    public function testNahookAPIErrorIsRetryableFor429(): void
    {
        $error = new \Nahook\Errors\NahookAPIError(429, 'rate_limited', 'Too many requests');
        $this->assertTrue($error->isRetryable());
        $this->assertTrue($error->isRateLimited());
    }

    public function testNahookAPIErrorIsNotRetryableFor4xx(): void
    {
        $error = new \Nahook\Errors\NahookAPIError(400, 'validation', 'Bad request');
        $this->assertFalse($error->isRetryable());
        $this->assertTrue($error->isValidationError());
    }

    public function testNahookAPIErrorIsAuthErrorFor401(): void
    {
        $error = new \Nahook\Errors\NahookAPIError(401, 'unauthorized', 'Unauthorized');
        $this->assertTrue($error->isAuthError());
    }

    public function testNahookAPIErrorIsAuthErrorFor403TokenDisabled(): void
    {
        $error = new \Nahook\Errors\NahookAPIError(403, 'token_disabled', 'Token disabled');
        $this->assertTrue($error->isAuthError());
    }

    public function testNahookAPIErrorIsNotAuthErrorFor403Other(): void
    {
        $error = new \Nahook\Errors\NahookAPIError(403, 'forbidden', 'Forbidden');
        $this->assertFalse($error->isAuthError());
    }

    public function testNahookAPIErrorIsNotFound(): void
    {
        $error = new \Nahook\Errors\NahookAPIError(404, 'not_found', 'Not found');
        $this->assertTrue($error->isNotFound());
    }

    public function testNahookTimeoutError(): void
    {
        $error = new \Nahook\Errors\NahookTimeoutError(30000);
        $this->assertSame(30000, $error->timeoutMs);
        $this->assertSame('Request timed out after 30000ms', $error->getMessage());
    }

    public function testNahookNetworkError(): void
    {
        $cause = new \RuntimeException('Connection refused');
        $error = new \Nahook\Errors\NahookNetworkError($cause);
        $this->assertSame($cause, $error->cause);
        $this->assertSame('Network error: Connection refused', $error->getMessage());
    }
}
