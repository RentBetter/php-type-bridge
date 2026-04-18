<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

final readonly class ShapeType extends ParsedType
{
    /**
     * @param list<ShapeField> $fields
     */
    public function __construct(
        public array $fields,
    ) {}
}
