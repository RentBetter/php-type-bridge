<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

final readonly class TupleType extends ParsedType
{
    /**
     * @param list<ParsedType> $elements
     * @param bool $unsealed `array{T, U, ...}`: more elements may follow the listed ones
     */
    public function __construct(
        public array $elements,
        public bool $unsealed = false,
    ) {}
}
