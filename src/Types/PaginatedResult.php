<?php

declare(strict_types=1);

namespace Nahook\Types;

/**
 * Cursor-paginated read result. Used by endpoints that paginate over
 * potentially large collections (e.g. deliveries). `$nextCursor` is an
 * opaque, server-encrypted token — pass it back verbatim on the next
 * request, do not decode or modify it. `null` when there are no more pages.
 *
 * PHP has no runtime generics; element type is conveyed via PHPDoc only.
 *
 * @template T
 */
final class PaginatedResult
{
    /**
     * @param array<int, T> $data
     */
    public function __construct(
        public readonly array $data,
        public readonly ?string $nextCursor,
    ) {
    }
}
