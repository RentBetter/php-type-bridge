<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

final readonly class IntersectionType extends ParsedType
{
    public function __construct(
        public NameRefType $base,
        public ShapeType $extra,
    ) {}
}
