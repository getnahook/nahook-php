<?php

declare(strict_types=1);

namespace Nahook\Resources;

use Nahook\HttpClient;

class SubscriptionsResource
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * @return array{data: array<mixed>}
     */
    public function list(string $workspaceId, string $endpointId): array
    {
        $data = $this->http->request([
            'method' => 'GET',
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId)
                . '/endpoints/' . rawurlencode($endpointId) . '/subscriptions',
        ]);

        return ['data' => $data];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function create(string $workspaceId, string $endpointId, array $options): array
    {
        return $this->http->request([
            'method' => 'POST',
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId)
                . '/endpoints/' . rawurlencode($endpointId) . '/subscriptions',
            'body' => $options,
        ]);
    }

    public function delete(string $workspaceId, string $endpointId, string $eventTypeId): void
    {
        $this->http->request([
            'method' => 'DELETE',
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId)
                . '/endpoints/' . rawurlencode($endpointId)
                . '/subscriptions/' . rawurlencode($eventTypeId),
        ]);
    }
}
