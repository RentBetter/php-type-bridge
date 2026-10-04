<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

use PTGS\TypeBridge\Parser\ShapeType;

final readonly class CollectedInputReference
{
    /**
     * @param class-string|null $formClass
     * @param class-string $ownerClass
     * @param list<CollectedFormField> $fields
     * @param ShapeType|null $contract the keys the input class declares as its `_self` shape — the
     *        request contract a client is typed against. Null when `_self` is not a shape that
     *        can be read as one (an alias, a generic), and then only the form speaks for a field
     */
    public function __construct(
        public ?string $formClass,
        public string $ownerClass,
        public string $typeName,
        public string $domain,
        public array $fields = [],
        public ?ShapeType $contract = null,
    ) {}
}
