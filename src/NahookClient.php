<?php

declare(strict_types=1);

namespace Nahook;

use Ramsey\Uuid\Uuid;

class NahookClient
{
    private readonly HttpClient $http;

    /**
     * @param string $apiKey Must start with 'nhk_'
     * @param array{
     *     baseUrl?: string,
     *     timeout?: int,
     *     retries?: int,
     *     handler?: \GuzzleHttp\HandlerStack
     * } $options
     */
    public function __construct(string $apiKey, array $options = [])
    {
        if (!str_starts_with($apiKey, 'nhk_')) {
            throw new \InvalidArgumentException("Invalid API key: must start with 'nhk_'");
        }

        $config = ['token' => $apiKey];
        if (isset($options['baseUrl'])) {
            $config['baseUrl'] = $options['baseUrl'];
        }
        if (isset($options['timeout'])) {
            $config['timeout'] = $options['timeout'];
        }
        if (isset($options['retries'])) {
            $config['retries'] = $options['retries'];
        }
        if (isset($options['handler'])) {
            $config['handler'] = $options['handler'];
        }
        $this->http = new HttpClient($config);
    }

    /**
     * Send a payload to a specific endpoint.
     *
     * @param string $endpointId
     * @param array{payload: array<string, mixed>, idempotencyKey?: string} $options
     * @return array{deliveryId: string, idempotencyKey: string, status: string}
     */
    public function send(string $endpointId, array $options): array
    {
        $idempotencyKey = $options['idempotencyKey'] ?? Uuid::uuid4()->toString();

        return $this->http->request([
            'method' => 'POST',
            'path' => '/api/ingest/' . rawurlencode($endpointId),
            'body' => [
                'payload' => $options['payload'],
                'idempotencyKey' => $idempotencyKey,
            ],
        ]);
    }

    /**
     * Fan-out a payload by event type to all subscribed endpoints.
     *
     * @param string $eventType
     * @param array{payload: array<string, mixed>, metadata?: array<string, string>} $options
     * @return array{eventTypeId: string, deliveryIds: string[], status: string}
     */
    public function trigger(string $eventType, array $options): array
    {
        $body = ['payload' => $options['payload']];
        if (isset($options['metadata'])) {
            $body['metadata'] = $options['metadata'];
        }

        return $this->http->request([
            'method' => 'POST',
            'path' => '/api/ingest/event/' . rawurlencode($eventType),
            'body' => $body,
        ]);
    }

    /**
     * Batch send to multiple specific endpoints (max 20 items).
     *
     * @param array<array{endpointId: string, payload: array<string, mixed>, idempotencyKey?: string}> $items
     * @return array{items: array<mixed>}
     */
    public function sendBatch(array $items): array
    {
        $result = $this->http->requestWithStatus([
            'method' => 'POST',
            'path' => '/api/ingest/batch',
            'body' => ['items' => $items],
        ]);

        return $result['data'];
    }

    /**
     * Batch fan-out by event types (max 20 items).
     *
     * @param array<array{eventType: string, payload: array<string, mixed>, metadata?: array<string, string>}> $items
     * @return array{items: array<mixed>}
     */
    public function triggerBatch(array $items): array
    {
        $result = $this->http->requestWithStatus([
            'method' => 'POST',
            'path' => '/api/ingest/event/batch',
            'body' => ['items' => $items],
        ]);

        return $result['data'];
    }
}
