<?php

declare(strict_types=1);

namespace Nahook\Resources;

use Nahook\HttpClient;

final class SubscriptionsResource
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * List event types subscribed to this endpoint.
     *
     * @return array{data: array<int, array{id: string, eventTypeId: string, eventTypeName: string, createdAt: string}>}
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
     * Subscribe an endpoint to one or more event types.
     *
     * @param string[] $eventTypeIds Array of event type public IDs (e.g. ["evt_..."])
     * @return array{subscribed: int}
     */
    public function create(string $workspaceId, string $endpointId, array $eventTypeIds): array
    {
        return $this->http->request([
            'method' => 'POST',
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId)
                . '/endpoints/' . rawurlencode($endpointId) . '/subscriptions',
            'body' => ['eventTypeIds' => $eventTypeIds],
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
