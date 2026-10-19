<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

/**
 * Walks a parsed type's parts.
 */
final class ParsedTypes
{
    /**
     * The types a type is made of, one level down: a nullable's or a list's inner type, a map's key
     * and value — a nested shape or a value-of enum can sit on either side — a tuple's elements, a
     * shape's key types, a union's members, an intersection's base and extra, a generic's arguments.
     *
     * @return list<ParsedType>
     */
    public static function children(ParsedType $type): array
    {
        return match (true) {
            $type instanceof NullableType, $type instanceof ListType => [$type->inner],
            $type instanceof MapType => [$type->key, $type->value],
            $type instanceof TupleType => $type->elements,
            $type instanceof ShapeType => array_map(static fn (ShapeField $field): ParsedType => $field->type, $type->fields),
            $type instanceof UnionType => $type->types,
            $type instanceof IntersectionType => [$type->base, $type->extra],
            $type instanceof GenericType => $type->arguments,
            default => [],
        };
    }

    /**
     * The names the types use as types, at any depth: aliases and classes, not a shape's keys or a
     * literal's text spelt the same.
     *
     * @return array<string, true>
     */
    public static function names(ParsedType ...$types): array
    {
        $names = [];
        while ([] !== $types) {
            $type = array_pop($types);
            if ($type instanceof NameRefType) {
                $names[$type->name] = true;
            }
            $types = [...$types, ...self::children($type)];
        }

        return $names;
    }
}
