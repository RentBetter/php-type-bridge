<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Http\Include;

/**
 * Where an expanded {@see Ref} stands in a body while IncludeResolver fetches its record: the
 * ref, its path, the include and expand paths that go on below it, and — once fetched and walked
 * — the record that takes its place.
 *
 * @internal to IncludeResolver
 */
final class ExpandedRef
{
    public mixed $value = null;

    public function __construct(
        public readonly Ref $ref,
        public readonly string $path,
        public readonly ?IncludeTree $include,
        public readonly IncludeTree $expand,
    ) {}
}
