<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

final readonly class CollectedInputReference
{
    /**
     * @param class-string|null $formClass
     * @param class-string $ownerClass
     * @param list<CollectedFormField> $fields
     */
    public function __construct(
        public ?string $formClass,
        public string $ownerClass,
        public string $typeName,
        public string $domain,
        public array $fields = [],
    ) {}
}
