<?php

declare(strict_types=1);

namespace Nahook\Types;

/**
 * One delivery attempt — represents a single HTTP call the worker made to the
 * subscriber's endpoint. `status` is an opaque worker-emitted string (e.g.
 * "failed", "success") — not modelled as an enum because the set may evolve.
 */
final class DeliveryAttempt
{
    public function __construct(
        public readonly string $id,
        public readonly int $attemptNumber,
        public readonly string $status,
        public readonly ?int $responseStatusCode,
        public readonly ?int $responseTimeMs,
        public readonly ?string $errorMessage,
        public readonly string $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            id: (string) $raw['id'],
            attemptNumber: (int) $raw['attemptNumber'],
            status: (string) $raw['status'],
            responseStatusCode: isset($raw['responseStatusCode']) ? (int) $raw['responseStatusCode'] : null,
            responseTimeMs: isset($raw['responseTimeMs']) ? (int) $raw['responseTimeMs'] : null,
            errorMessage: isset($raw['errorMessage']) ? (string) $raw['errorMessage'] : null,
            createdAt: (string) $raw['createdAt'],
        );
    }
}
