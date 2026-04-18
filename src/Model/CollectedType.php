<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

use PTGS\TypeBridge\Parser\ParsedType;

final readonly class CollectedType
{
    /**
     * @param list<ImportedType> $imports
     */
    public function __construct(
        public string $name,
        public string $definition,
        public ParsedType $parsed,
        public string $sourceFile,
        public string $domain,
        public string $ownerClass,
        public array $imports = [],
    ) {}
}
