<?php

declare(strict_types=1);

namespace Nahook\Resources;

use Nahook\HttpClient;
use Nahook\Types\Delivery;
use Nahook\Types\DeliveryAttempt;
use Nahook\Types\DeliveryWithPayload;
use Nahook\Types\GetDeliveryOptions;
use Nahook\Types\ListDeliveriesOptions;
use Nahook\Types\PaginatedResult;

/**
 * Read access to a workspace's webhook deliveries. All methods are paginated
 * or single-resource reads — there is no create/update/delete on this resource.
 *
 * Deliveries are scoped to an endpoint for `list()` because the regional
 * deliveries table is indexed by endpoint id; there is no workspace-wide
 * index. Single `get()` and `getAttempts()` accept a delivery public id
 * directly.
 */
final class DeliveriesResource
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * List deliveries for an endpoint, newest-first, cursor-paginated.
     *
     * @param ListDeliveriesOptions|array{limit?: int, cursor?: string, status?: string}|null $options
     * @return PaginatedResult<Delivery>
     */
    public function list(
        string $workspaceId,
        string $endpointId,
        ListDeliveriesOptions|array|null $options = null,
    ): PaginatedResult {
        $opts = $this->normalizeListOptions($options);

        /** @var array{deliveries: array<int, array<string, mixed>>, nextCursor: ?string} $raw */
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId)
                . '/endpoints/' . rawurlencode($endpointId) . '/deliveries',
            'query' => [
                'limit' => $opts->limit,
                'cursor' => $opts->cursor,
                'status' => $opts->status,
            ],
        ]);

        $data = array_map(
            static fn(array $row): Delivery => Delivery::fromArray($row),
            $raw['deliveries'],
        );

        return new PaginatedResult(
            data: $data,
            nextCursor: $raw['nextCursor'],
        );
    }

    /**
     * Fetch a single delivery by public id. When `includePayload` is true the
     * response includes a `PayloadEnvelope` describing the original webhook
     * body's access status — see `PayloadEnvelope` for the 5 possible states.
     *
     * @param GetDeliveryOptions|array{includePayload?: bool}|null $options
     */
    public function get(
        string $workspaceId,
        string $deliveryId,
        GetDeliveryOptions|array|null $options = null,
    ): DeliveryWithPayload {
        $opts = $this->normalizeGetOptions($options);

        /** @var array<string, mixed> $raw */
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId)
                . '/deliveries/' . rawurlencode($deliveryId),
            'query' => $opts->includePayload ? ['include' => 'payload'] : [],
        ]);

        return DeliveryWithPayload::fromArray($raw);
    }

    /**
     * List the attempt history for a delivery, chronological (oldest first).
     *
     * @return DeliveryAttempt[]
     */
    public function getAttempts(string $workspaceId, string $deliveryId): array
    {
        /** @var array<int, array<string, mixed>> $raw */
        $raw = $this->http->request([
            'method' => 'GET',
            'path' => '/management/v1/workspaces/' . rawurlencode($workspaceId)
                . '/deliveries/' . rawurlencode($deliveryId) . '/attempts',
        ]);

        return array_map(
            static fn(array $row): DeliveryAttempt => DeliveryAttempt::fromArray($row),
            $raw,
        );
    }

    /**
     * @param ListDeliveriesOptions|array{limit?: int, cursor?: string, status?: string}|null $options
     */
    private function normalizeListOptions(ListDeliveriesOptions|array|null $options): ListDeliveriesOptions
    {
        if ($options instanceof ListDeliveriesOptions) {
            return $options;
        }
        $options ??= [];
        return new ListDeliveriesOptions(
            limit: $options['limit'] ?? null,
            cursor: $options['cursor'] ?? null,
            status: $options['status'] ?? null,
        );
    }

    /**
     * @param GetDeliveryOptions|array{includePayload?: bool}|null $options
     */
    private function normalizeGetOptions(GetDeliveryOptions|array|null $options): GetDeliveryOptions
    {
        if ($options instanceof GetDeliveryOptions) {
            return $options;
        }
        $options ??= [];
        return new GetDeliveryOptions(
            includePayload: $options['includePayload'] ?? false,
        );
    }
}
