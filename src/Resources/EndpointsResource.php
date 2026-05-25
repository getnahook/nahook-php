<?php

declare(strict_types=1);

namespace Nahook\Resources;

use Nahook\HttpClient;

final class EndpointsResource
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
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId) . '/endpoints',
        ]);

        return ['data' => $data];
    }

    /**
     * Create an endpoint.
     *
     * @param array<string, mixed> $options Endpoint fields. Recognized keys:
     *   - url (string, required)
     *   - type (string, optional)
     *   - description (string, optional)
     *   - metadata (array<string,string>, optional)
     *   - config (array<string,mixed>, optional)
     *   - authUsername / authPassword (string, optional)
     *   - environmentId (string, optional) Public id (e.g. "env_abc123") of the
     *     environment to scope this endpoint. If omitted, the workspace's
     *     default environment is used.
     * @return array<string, mixed>
     */
    public function create(string $workspaceId, array $options): array
    {
        return $this->http->request([
            'method' => 'POST',
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId) . '/endpoints',
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
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId) . '/endpoints/' . rawurlencode($id),
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
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId) . '/endpoints/' . rawurlencode($id),
            'body' => $options,
        ]);
    }

    public function delete(string $workspaceId, string $id): void
    {
        $this->http->request([
            'method' => 'DELETE',
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId) . '/endpoints/' . rawurlencode($id),
        ]);
    }
}
