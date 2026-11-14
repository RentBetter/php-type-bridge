<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

use LogicException;

/**
 * Walks a parsed type's parts.
 */
final class ParsedTypes
{
    /**
     * The types a type is made of, one level down: a nullable's or a list's inner type, a map's key
     * and value — a nested shape or a value-of enum can sit on either side — a tuple's elements, a
     * shape's key types, a union's members, an intersection's base and extra, a generic's arguments,
     * the type a scope holds.
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
            $type instanceof ScopedType => [$type->inner],
            default => [],
        };
    }

    /**
     * The type a scope holds, however many wrap it ({@see ScopedType}): a name, or an intersection,
     * read where it was written.
     */
    public static function unscoped(ParsedType $type): ParsedType
    {
        while ($type instanceof ScopedType) {
            $type = $type->inner;
        }

        return $type;
    }

    /**
     * The type made of these parts in place of its own ({@see self::children()}), in the same order:
     * the type itself where it has none.
     *
     * @param list<ParsedType> $children
     */
    public static function withChildren(ParsedType $type, array $children): ParsedType
    {
        return match (true) {
            $type instanceof NullableType => new NullableType(inner: $children[0], optional: $type->optional),
            $type instanceof ListType => new ListType($children[0]),
            $type instanceof MapType => new MapType($children[0], $children[1]),
            $type instanceof TupleType => new TupleType($children, unsealed: $type->unsealed),
            $type instanceof ShapeType => self::shapeWith($type, $children),
            $type instanceof UnionType => new UnionType($children),
            $type instanceof IntersectionType => new IntersectionType(base: $children[0], extra: $children[1] instanceof ShapeType ? $children[1] : throw new LogicException('An intersection\'s extra is a shape.')),
            $type instanceof GenericType => new GenericType($type->name, $children),
            $type instanceof ScopedType => new ScopedType($children[0], $type->imports, $type->domain),
            default => $type,
        };
    }

    /**
     * @param list<ParsedType> $types
     */
    private static function shapeWith(ShapeType $shape, array $types): ShapeType
    {
        $fields = [];
        foreach ($shape->fields as $i => $field) {
            $fields[] = new ShapeField(name: $field->name, type: $types[$i], optional: $field->optional);
        }

        return new ShapeType($fields, unsealed: $shape->unsealed);
    }

    /**
     * The names the types use bare as types, at any depth — an alias's, or a class's not yet resolved
     * — not a name qualified or resolved as a class's, a shape's keys, or a literal's text spelt the
     * same: those an import or an alias of the file answers to.
     *
     * @return array<string, true>
     */
    public static function names(ParsedType ...$types): array
    {
        $names = [];
        while ([] !== $types) {
            $type = array_pop($types);
            if ($type instanceof NameRefType && null === $type->class) {
                $names[$type->name] = true;
            }
            // Written in another file: its names are that file's.
            if ($type instanceof ScopedType) {
                continue;
            }
            array_push($types, ...self::children($type));
        }

        return $names;
    }
}
