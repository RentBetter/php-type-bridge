<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

/**
 * `id-of<Enum>` — the union of an enum's stable string ids, as opposed to
 * {@see ValueOfType}'s union of its backing values.
 *
 * The two differ whenever an enum's wire identity is not its backing value: an
 * int-backed status enum has no string backing value at all, and an enum may compute
 * or override its id. The ids therefore cannot be derived from the class statically —
 * they are named here and resolved by whichever emitter publishes them.
 */
final readonly class IdOfType extends ParsedType
{
    public function __construct(
        public string $enumClass, // Fully qualified or short class name
    ) {}
}
