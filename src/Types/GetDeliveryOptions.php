<?php

declare(strict_types=1);

namespace Nahook\Types;

/**
 * Optional flag for `DeliveriesResource::get()`.
 *
 * When `$includePayload` is true the SDK appends `?include=payload` and the
 * server returns the delivery with a `PayloadEnvelope` attached.
 */
final class GetDeliveryOptions
{
    public function __construct(
        public readonly bool $includePayload = false,
    ) {
    }
}
