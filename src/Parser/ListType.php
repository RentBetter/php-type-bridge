<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

final readonly class ListType extends ParsedType
{
    public function __construct(
        public ParsedType $inner,
    ) {}
}
