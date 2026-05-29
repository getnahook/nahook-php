<?php

declare(strict_types=1);

namespace Nahook\Types;

/**
 * Envelope returned when `get()` is called with `includePayload: true`.
 *
 * PHP has no native sum types — we model this as a flat readonly class whose
 * `status` discriminator carries one of 5 values, and whose `data` /
 * `contentType` fields are populated only when `status === "available"`. All
 * other statuses describe why the payload is not (yet/ever) accessible:
 *
 *   - "available"  — payload retrieved + decrypted; check $data / $contentType
 *   - "forbidden"  — workspace plan does not include payload storage
 *   - "processing" — delivery still in flight, R2 write may be racing the read
 *   - "not_found"  — terminal delivery without stored payload
 *   - "error"      — transient infrastructure failure
 *
 * The endpoint returns HTTP 200 for all 5 — the envelope `status` carries the
 * access-level reality. Do **not** treat non-"available" as an exception.
 */
final class PayloadEnvelope
{
    public function __construct(
        public readonly string $status,
        public readonly mixed $data = null,
        public readonly ?string $contentType = null,
    ) {
    }

    /**
     * @param array<string, mixed> $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            status: (string) $raw['status'],
            data: $raw['data'] ?? null,
            contentType: isset($raw['contentType']) ? (string) $raw['contentType'] : null,
        );
    }
}
