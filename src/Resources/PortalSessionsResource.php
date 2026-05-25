<?php

declare(strict_types=1);

namespace Nahook\Resources;

use Nahook\HttpClient;

final class PortalSessionsResource
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * Create a portal session for a workspace application.
     *
     * @param array{
     *     metadata?: array<string, string>,
     *     role?: string,
     *     expiresInMinutes?: int,
     * } $options
     * @return array<string, mixed>
     */
    public function create(string $workspaceId, string $appId, array $options = []): array
    {
        return $this->http->request([
            'method' => 'POST',
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId)
                . '/applications/' . rawurlencode($appId) . '/portal',
            'body' => empty($options) ? (object) [] : $options,
        ]);
    }
}
