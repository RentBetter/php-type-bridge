<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

/**
 * A literal value type: 'draft', 42, -1, 3.14, true, false.
 *
 * Emitted to TypeScript as the corresponding literal type.
 */
final readonly class LiteralType extends ParsedType
{
    public function __construct(
        public string|int|float|bool $value,
    ) {}
}
