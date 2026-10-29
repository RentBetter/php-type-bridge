<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

final readonly class ShapeType extends ParsedType
{
    /**
     * @param list<ShapeField> $fields
     * @param bool $unsealed `array{…, ...}`: keys beyond the listed ones may be there too
     */
    public function __construct(
        public array $fields,
        public bool $unsealed = false,
    ) {}

    /**
     * The keys a type declares itself, read as a shape: a shape's, or an intersection's own — what it
     * inherits is declared where its base is. Null for a type that is neither.
     */
    public static function declaredBy(?ParsedType $type): ?self
    {
        return match (true) {
            $type instanceof self => $type,
            $type instanceof IntersectionType => $type->extra,
            default => null,
        };
    }

    /**
     * Every key an input declares, those it inherits included, as the intersection reads them: its
     * own alone where what it inherits can't be read; null where it declares no shape.
     */
    public static function withInherited(?self $own, ?self $inherited): ?self
    {
        return null === $own || null === $inherited ? $own : self::intersected($inherited, $own);
    }

    /**
     * The keys `$base & $own` has, as TypeScript reads an intersection: every key of either, one
     * both declare {@see ShapeField::intersected()} — so every reader of an input's keys, the
     * Endpoint and the tool alike, reads one set. Open to more keys where either is.
     */
    public static function intersected(self $base, self $own): self
    {
        $keys = [];
        foreach ($base->fields as $key) {
            $keys[$key->name] = $key;
        }
        foreach ($own->fields as $key) {
            $keys[$key->name] = isset($keys[$key->name]) ? ShapeField::intersected($keys[$key->name], $key) : $key;
        }

        return new self(array_values($keys), $base->unsealed || $own->unsealed);
    }
}
