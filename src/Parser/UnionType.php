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
}
