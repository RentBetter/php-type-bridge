<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

use PTGS\TypeBridge\Parser\ParsedType;

final readonly class CollectedType
{
    /**
     * @param list<ImportedType> $imports
     * @param bool $isSelf declared as `_self`: the shape of the owner class itself, so a shape
     *        that names the class means this
     */
    public function __construct(
        public string $name,
        public string $definition,
        public ParsedType $parsed,
        public string $sourceFile,
        public string $domain,
        public string $ownerClass,
        public array $imports = [],
        public bool $isSelf = false,
    ) {}
}
