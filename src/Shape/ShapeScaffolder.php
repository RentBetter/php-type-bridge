<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Shape;

use DateTimeInterface;
use PTGS\TypeBridge\Model\CollectedFormField;
use PTGS\TypeBridge\Parser\ListType;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\ParsedType;
use PTGS\TypeBridge\Parser\ScalarType;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Parser\ValueOfType;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Derives a `_self` shape for an input DTO from the form that binds it.
 *
 * Adopting #[ApiRequest] means every input contract must declare `@phpstan-type _self`, which for
 * an existing application is dozens of shapes written by hand — the sort of migration that gets
 * done carelessly or not at all. This writes the first draft from the two things that actually
 * define the contract:
 *
 * - the **form**, which is the authority on what keys exist on the wire and whether each is
 *   required, and already knows an EnumType's enum and a compound field's children;
 * - the **DTO**, which is the authority on the PHP type behind each key.
 *
 * Neither alone is enough — a form field's type says `TextType` where the DTO says the property is
 * a `DateTimeImmutable`, and the DTO cannot say which of its properties the form actually exposes.
 *
 * A field it cannot type confidently is reported rather than guessed. A shape that is subtly wrong
 * is worse than an absent one: it becomes the published contract, and every client believes it.
 */
final class ShapeScaffolder
{
    /**
     * @param list<CollectedFormField> $fields
     *
     * @return array{shape: ShapeType, unresolved: list<string>}
     */
    public function scaffold(string $dataClass, array $fields): array
    {
        $properties = $this->propertyTypes($dataClass);

        $shapeFields = [];
        $unresolved = [];

        foreach ($fields as $field) {
            if (!$field->mapped) {
                continue;
            }

            $type = $this->fieldType($field, $properties[$field->name] ?? null);
            if (null === $type) {
                $unresolved[] = $field->name;

                continue;
            }

            // Nullable in PHP is the signal, not the form's `required`: a field can be optional on
            // the wire while the DTO still guarantees a value via a default.
            $nullable = $properties[$field->name]['nullable'] ?? !$field->required;
            $shapeFields[] = new ShapeField(
                name: $field->name,
                type: $nullable ? new NullableType($type, optional: true) : $type,
                optional: false,
            );
        }

        return ['shape' => new ShapeType($shapeFields), 'unresolved' => $unresolved];
    }

    /**
     * @param array{type: ?ParsedType, nullable: bool}|null $property the DTO's own declaration
     */
    private function fieldType(CollectedFormField $field, ?array $property): ?ParsedType
    {
        if (null !== $field->enumClass) {
            return new ValueOfType($this->shortName($field->enumClass));
        }

        if ($field->compound && [] !== $field->children) {
            $children = [];
            foreach ($field->children as $child) {
                $childType = $this->fieldType($child, null);
                if (null === $childType) {
                    return null;
                }
                $children[] = new ShapeField($child->name, $childType, optional: !$child->required);
            }

            return new ShapeType($children);
        }

        if (null !== $field->entryTypeClass) {
            $entry = $this->scalarFromFormType($field->entryTypeClass);

            return null === $entry ? null : new ListType($entry);
        }

        // The DTO's own type wins where it is decisive — a date lands as a string on the wire, and
        // only the property says so.
        if (null !== $property && null !== $property['type']) {
            return $property['type'];
        }

        return $this->scalarFromFormType($field->formTypeClass);
    }

    private function scalarFromFormType(string $formTypeClass): ?ScalarType
    {
        $shortName = $this->shortName($formTypeClass);

        return match (true) {
            str_contains($shortName, 'Checkbox'), str_contains($shortName, 'Boolean') => new ScalarType('bool'),
            str_contains($shortName, 'Integer') => new ScalarType('int'),
            str_contains($shortName, 'Number'), str_contains($shortName, 'Money') => new ScalarType('float'),
            str_contains($shortName, 'Text'), str_contains($shortName, 'Textarea'),
            str_contains($shortName, 'Email'), str_contains($shortName, 'Url'),
            str_contains($shortName, 'Uuid'), str_contains($shortName, 'Date'),
            str_contains($shortName, 'Time') => new ScalarType('string'),
            default => null,
        };
    }

    /**
     * @return array<string, array{type: ?ParsedType, nullable: bool}>
     */
    private function propertyTypes(string $dataClass): array
    {
        if (!class_exists($dataClass)) {
            return [];
        }

        $types = [];
        foreach ((new ReflectionClass($dataClass))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $type = $property->getType();
            $types[$property->getName()] = [
                'type' => $type instanceof ReflectionNamedType ? $this->fromNamedType($type) : null,
                'nullable' => $type?->allowsNull() ?? true,
            ];
        }

        return $types;
    }

    private function fromNamedType(ReflectionNamedType $type): ?ParsedType
    {
        $name = $type->getName();

        return match (true) {
            'string' === $name => new ScalarType('string'),
            'int' === $name => new ScalarType('int'),
            'float' === $name => new ScalarType('float'),
            'bool' === $name => new ScalarType('bool'),
            is_a($name, DateTimeInterface::class, allow_string: true) => new ScalarType('string'),
            enum_exists($name) => new ValueOfType($this->shortName($name)),
            // Arrays need their @var docblock to be typed at all, and object properties need a
            // shape of their own — both are reported rather than guessed.
            default => null,
        };
    }

    private function shortName(string $className): string
    {
        $position = strrpos($className, '\\');

        return false === $position ? $className : substr($className, $position + 1);
    }
}
