<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

/**
 * A positional `array{T, U}` — PHPStan's tuple form, where elements are ordered rather than
 * named. Distinct from {@see ShapeType}, whose fields are keyed: a tuple emits as the
 * TypeScript tuple `[T, U]`, which is a different type from an object with numeric keys.
 */
final readonly class TupleType extends ParsedType
{
    /**
     * @param list<ParsedType> $elements
     */
    public function __construct(
        public array $elements,
    ) {}
}
