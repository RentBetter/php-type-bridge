<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

final readonly class ImportedType
{
    public function __construct(
        public string $localAlias,
        public string $targetClass,
        public string $targetTypeName,
        public string $targetDomain,
    ) {}
}
