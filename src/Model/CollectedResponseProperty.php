<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

use PTGS\TypeBridge\Parser\ParsedType;

final readonly class CollectedResponseProperty
{
    public function __construct(
        public string $name,
        public string $rawType,
        public ParsedType $parsed,
        public bool $optional = false,
    ) {}
}
