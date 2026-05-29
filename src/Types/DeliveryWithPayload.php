<?php

declare(strict_types=1);

namespace Nahook\Types;

/**
 * A delivery returned by `get()`, optionally including the payload envelope.
 *
 * `$payload` is populated only when the request was made with
 * `includePayload: true`; otherwise it is null and callers should not access it.
 */
final class DeliveryWithPayload
{
    public function __construct(
        public readonly string $id,
        public readonly string $idempotencyKey,
        public readonly string $endpointId,
        public readonly string $status,
        public readonly int $totalAttempts,
        public readonly ?string $firstAttemptAt,
        public readonly ?string $deliveredAt,
        public readonly ?string $nextRetryAt,
        public readonly bool $hasPayload,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly ?PayloadEnvelope $payload = null,
    ) {
    }

    /**
     * @param array<string, mixed> $raw
     */
    public static function fromArray(array $raw): self
    {
        $payload = null;
        if (isset($raw['payload']) && is_array($raw['payload'])) {
            $payload = PayloadEnvelope::fromArray($raw['payload']);
        }

        return new self(
            id: (string) $raw['id'],
            idempotencyKey: (string) $raw['idempotencyKey'],
            endpointId: (string) $raw['endpointId'],
            status: (string) $raw['status'],
            totalAttempts: (int) $raw['totalAttempts'],
            firstAttemptAt: isset($raw['firstAttemptAt']) ? (string) $raw['firstAttemptAt'] : null,
            deliveredAt: isset($raw['deliveredAt']) ? (string) $raw['deliveredAt'] : null,
            nextRetryAt: isset($raw['nextRetryAt']) ? (string) $raw['nextRetryAt'] : null,
            hasPayload: (bool) $raw['hasPayload'],
            createdAt: (string) $raw['createdAt'],
            updatedAt: (string) $raw['updatedAt'],
            payload: $payload,
        );
    }
}
