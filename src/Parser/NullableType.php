<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

final readonly class NullableType extends ParsedType
{
    public function __construct(
        public ParsedType $inner,
        public bool $optional, // true = ?string (TS optional), false = string|null (TS explicit null)
    ) {}
}
