<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

final readonly class CollectedApiResponseClass
{
    /**
     * @param list<CollectedResponseProperty> $properties
     * @param list<ImportedType> $imports what the response's own file imports and declares. Each
     *        property carries the imports of the file it is written in, which is what it is
     *        converted against; this is the class's own scope, for an emitter that wants it
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
