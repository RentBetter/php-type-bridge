<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

final readonly class CollectedApiResponseClass
{
    /**
     * @param list<CollectedResponseProperty> $properties
     * @param list<ImportedType> $imports
     */
    public function __construct(
        public string $className,
        public string $name,
        public string $domain,
        public string $sourceFile,
        public int $status,
        public bool $error,
        public array $properties,
        public array $imports = [],
    ) {}
}
