<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

final readonly class NameRefType extends ParsedType
{
    public function __construct(
        public string $name, // e.g. 'IProjectBase'
    ) {}
}
