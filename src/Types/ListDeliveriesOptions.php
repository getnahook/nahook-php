<?php

declare(strict_types=1);

namespace Nahook\Types;

/**
 * Optional filters for `DeliveriesResource::list()`.
 *
 * `$cursor` is opaque — pass `$nextCursor` from the previous page verbatim.
 * `$status` accepts one of: "pending", "delivering", "delivered",
 * "scheduled_retry", "failed", "dead_letter".
 */
final class ListDeliveriesOptions
{
    public function __construct(
        public readonly ?int $limit = null,
        public readonly ?string $cursor = null,
        public readonly ?string $status = null,
    ) {
    }
}
