<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

final readonly class ValueOfType extends ParsedType
{
    public function __construct(
        public string $enumClass, // Fully qualified or short class name
    ) {}
}
