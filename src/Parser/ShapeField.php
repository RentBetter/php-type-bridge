<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

final readonly class ShapeField
{
    public function __construct(
        public string $name,
        public ParsedType $type,
        public bool $optional, // PHPStan optional key syntax: ?key: type
    ) {}
}
