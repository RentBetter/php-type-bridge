<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

final readonly class CollectedFormField
{
    /**
     * @param list<CollectedFormField> $children
     */
    public function __construct(
        public string $name,
        public string $formTypeClass,
        public bool $required,
        public bool $mapped,
        public bool $compound,
        public ?string $dataClass,
        public ?string $propertyPath = null,
        public ?string $entryTypeClass = null,
        public ?string $entryDataClass = null,
        public ?string $enumClass = null,
        public ?string $input = null,
        public bool $hasModelTransformers = false,
        public bool $hasViewTransformers = false,
        public array $children = [],
    ) {}
}
