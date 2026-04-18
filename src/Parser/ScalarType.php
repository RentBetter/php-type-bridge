<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

final readonly class ScalarType extends ParsedType
{
    public function __construct(
        public string $type, // 'string', 'int', 'float', 'bool', 'mixed'
    ) {}
}
