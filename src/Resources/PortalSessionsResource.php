<?php

declare(strict_types=1);

namespace Nahook\Resources;

use Nahook\HttpClient;

class PortalSessionsResource
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * @param array<string, mixed> $options
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
