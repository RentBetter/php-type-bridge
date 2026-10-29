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
     *        can be read as one (an alias, a generic), and then only the form speaks for a field.
     *        For an intersection (`Base & array{…}`), the keys it adds
     * @param ShapeType|null $inheritedContract for an intersection, the keys it takes from the shape
     *        it extends, read where that shape is declared — a query's and a body's alike; null when
     *        `_self` is not one, or its base can't be read as a shape
     */
    public function __construct(
        public ?string $formClass,
        public string $ownerClass,
        public string $typeName,
        public string $domain,
        public array $fields = [],
        public ?ShapeType $contract = null,
        public ?ShapeType $inheritedContract = null,
    ) {}

    /**
     * Every key the input class declares, those it inherits included, as the intersection reads them
     * ({@see ShapeType::intersected()}): what the Endpoint and the tool both read. Its own alone where
     * what it inherits can't be read; null where `_self` is not a shape.
     */
    public function keys(): ?ShapeType
    {
        return ShapeType::withInherited($this->contract, $this->inheritedContract);
    }
}
