<?php

declare(strict_types=1);

namespace Nahook\Resources;

use Nahook\HttpClient;

final class ApplicationsResource
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * @param array{limit?: int, offset?: int} $options
     * @return array{data: array<mixed>}
     */
    public function list(string $workspaceId, array $options = []): array
    {
        $data = $this->http->request([
            'method' => 'GET',
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId) . '/applications',
            'query' => [
                'limit' => $options['limit'] ?? null,
                'offset' => $options['offset'] ?? null,
            ],
        ]);

        return ['data' => $data];
    }

    /**
     * Create an application.
     *
     * Supported keys: `name` (required), `externalId`, `metadata`,
     * `maxEndpoints` (int|null — cap on endpoints, disabled ones count;
     * 0 = read-only, omit/null = unlimited), `showEventTypes`
     * (bool — whether the Developer Portal exposes the event-type
     * catalog; server defaults to true).
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function create(string $workspaceId, array $options): array
    {
        return $this->http->request([
            'method' => 'POST',
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId) . '/applications',
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
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId) . '/applications/' . rawurlencode($id),
        ]);
    }

    /**
     * Update an application.
     *
     * `maxEndpoints` is tri-state: leave the key out to keep the current
     * cap, set it to `null` to clear the cap (unlimited), or set an int
     * (>= 0) to enforce one. `showEventTypes` is unchanged when omitted.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function update(string $workspaceId, string $id, array $options): array
    {
        return $this->http->request([
            'method' => 'PATCH',
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId) . '/applications/' . rawurlencode($id),
            'body' => $options,
        ]);
    }

    public function delete(string $workspaceId, string $id): void
    {
        $this->http->request([
            'method' => 'DELETE',
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId) . '/applications/' . rawurlencode($id),
        ]);
    }

    /**
     * @return array{data: array<mixed>}
     */
    public function listEndpoints(string $workspaceId, string $appId): array
    {
        $data = $this->http->request([
            'method' => 'GET',
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId)
                . '/applications/' . rawurlencode($appId) . '/endpoints',
        ]);

        return ['data' => $data];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function createEndpoint(string $workspaceId, string $appId, array $options): array
    {
        return $this->http->request([
            'method' => 'POST',
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId)
                . '/applications/' . rawurlencode($appId) . '/endpoints',
            'body' => $options,
        ]);
    }
}
