<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

final readonly class ImportedType
{
    /**
     * @param string|null $sourceAlias the alias as the target declares it — `_self`, or a named
     *        alias that may itself be one the target imports — where the import says so
     */
    public function __construct(
        public string $localAlias,
        public string $targetClass,
        public string $targetTypeName,
        public string $targetDomain,
        public ?string $sourceAlias = null,
    ) {}
}
