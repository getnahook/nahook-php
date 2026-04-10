<?php

declare(strict_types=1);

namespace Nahook\Resources;

use Nahook\HttpClient;

final class EventTypesResource
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * @return array{data: array<mixed>}
     */
    public function list(string $workspaceId): array
    {
        $data = $this->http->request([
            'method' => 'GET',
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId) . '/event-types',
        ]);

        return ['data' => $data];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function create(string $workspaceId, array $options): array
    {
        return $this->http->request([
            'method' => 'POST',
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId) . '/event-types',
            'body' => $options,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $workspaceId, string $id): array
    {
        return $this->http->request([
            'method' => 'GET',
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId) . '/event-types/' . rawurlencode($id),
        ]);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function update(string $workspaceId, string $id, array $options): array
    {
        return $this->http->request([
            'method' => 'PATCH',
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId) . '/event-types/' . rawurlencode($id),
            'body' => $options,
        ]);
    }

    public function delete(string $workspaceId, string $id): void
    {
        $this->http->request([
            'method' => 'DELETE',
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId) . '/event-types/' . rawurlencode($id),
        ]);
    }
}
