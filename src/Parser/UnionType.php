<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

final readonly class UnionType extends ParsedType
{
    /**
     * @param list<ParsedType> $types
     */
    public function __construct(
        public array $types,
    ) {}

    /**
     * Whether null is among its members: `'open'|'closed'|null`, which parses as a union, not a nullable.
     */
    public function hasNull(): bool
    {
        return array_any($this->types, ScalarType::isNull(...));
    }
}
