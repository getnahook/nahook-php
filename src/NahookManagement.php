<?php

declare(strict_types=1);

namespace Nahook;

use Nahook\Resources\ApplicationsResource;
use Nahook\Resources\EndpointsResource;
use Nahook\Resources\EventTypesResource;
use Nahook\Resources\PortalSessionsResource;
use Nahook\Resources\SubscriptionsResource;

class NahookManagement
{
    public readonly EndpointsResource $endpoints;
    public readonly EventTypesResource $eventTypes;
    public readonly ApplicationsResource $applications;
    public readonly SubscriptionsResource $subscriptions;
    public readonly PortalSessionsResource $portalSessions;

    /**
     * @param string $token Must start with 'nhm_'
     * @param array{
     *     baseUrl?: string,
     *     timeout?: int,
     *     handler?: \GuzzleHttp\HandlerStack
     * } $options
     */
    public function __construct(string $token, array $options = [])
    {
        if (!str_starts_with($token, 'nhm_')) {
            throw new \InvalidArgumentException("Invalid management token: must start with 'nhm_'");
        }

        $config = ['token' => $token];
        if (isset($options['baseUrl'])) {
            $config['baseUrl'] = $options['baseUrl'];
        }
        if (isset($options['timeout'])) {
            $config['timeout'] = $options['timeout'];
        }
        if (isset($options['handler'])) {
            $config['handler'] = $options['handler'];
        }
        $http = new HttpClient($config);

        $this->endpoints = new EndpointsResource($http);
        $this->eventTypes = new EventTypesResource($http);
        $this->applications = new ApplicationsResource($http);
        $this->subscriptions = new SubscriptionsResource($http);
        $this->portalSessions = new PortalSessionsResource($http);
    }
}
