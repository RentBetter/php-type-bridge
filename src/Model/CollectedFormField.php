<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

use Symfony\Component\Validator\Constraint;

final readonly class CollectedFormField
{
    /**
     * @param bool                     $multiple      the field's `multiple` option — an EnumType or
     *                                                ChoiceType carrying it holds a list of its leaf
     *                                                type, not one of them
     * @param list<CollectedFormField> $children      the fields of a compound field
     * @param list<CollectedFormField> $entryChildren the fields of a collection field's compound entry type —
     *                                                collections have no children of their own at build time,
     *                                                only a prototype, so the entry's shape is inspected separately
     * @param list<string>|null        $choiceValues  the values a choice field accepts, as its built form reads
     *                                                a submitted one — its choice list's values, narrowed by an
     *                                                `Assert\Choice` naming its choices; null for a field with no
     *                                                choice list
     * @param list<Constraint>         $constraints   what a submitted value is validated against: the
     *                                                field's `constraints` option, then the constraint
     *                                                attributes on the property it binds — Default group only
     * @param string|null              $ownerClass    the data class declaring the property the field binds,
     *                                                named by `$propertyPath`; null when it binds none directly
     *                                                (unmapped, or a deeper path)
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
        public bool $multiple = false,
        public bool $hasModelTransformers = false,
        public bool $hasViewTransformers = false,
        public array $children = [],
        public array $entryChildren = [],
        public ?array $choiceValues = null,
        public array $constraints = [],
        public ?string $ownerClass = null,
    ) {}
}
