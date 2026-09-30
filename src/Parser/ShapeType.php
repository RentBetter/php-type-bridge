<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

final readonly class ShapeType extends ParsedType
{
    /**
     * @param list<ShapeField> $fields
     * @param bool $unsealed `array{…, ...}`: keys beyond the listed ones may be there too
     */
    public function __construct(
        public array $fields,
        public bool $unsealed = false,
    ) {}
}
