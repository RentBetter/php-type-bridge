<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

final readonly class ShapeField
{
    public function __construct(
        public string $name,
        public ParsedType $type,
        public bool $optional, // PHPStan optional key syntax: ?key: type
    ) {}

    /**
     * Whether the key may be left out: `key?: T`, or `key: ?T`, which is emitted `key?: T` and left
     * off the wire when null. What a typed client, a tool's schema and an Endpoint all read it as.
     *
     * In a query, a key that may be null may be left out too: a query string carries no null, so
     * leaving the key out is how one is sent.
     */
    public function mayBeLeftOut(bool $inQuery = false): bool
    {
        return $this->optional || ($this->type instanceof NullableType && $this->type->optional) || ($inQuery && self::admitsNull($this->type));
    }

    /**
     * Whether a type admits null as a value sent: `T|null`, a union with a null among several
     * members (`'open'|'closed'|null`, which parses as a union, not a nullable), or `mixed`, which
     * admits anything — its TypeScript `unknown` too. `?T` does only where T does: it is a key left
     * out when null, and a project's preserveNull rule holds every nullable key to `?T` but the ones
     * whose null means something.
     */
    public static function admitsNull(ParsedType $type): bool
    {
        // `?T` is a key left out when null, `key?: T` in the TypeScript: it may be sent null only where
        // T may — an alias of `string|null` read through, say.
        if ($type instanceof NullableType) {
            return !$type->optional || self::admitsNull($type->inner);
        }
        if ($type instanceof ScalarType) {
            return 'mixed' === $type->base();
        }
        if (!$type instanceof UnionType) {
            return false;
        }
        foreach ($type->types as $member) {
            if ($member instanceof ScalarType && \in_array($member->base(), ['null', 'mixed'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The key `Base & Own` has where both declare it, as TypeScript reads the intersection: required
     * where either requires it, admitting null only where both do — otherwise the own key's type
     * without its null — its type otherwise the own one's, which narrows the base's, but for a shape
     * both declare, whose keys are intersected in turn.
     */
    public static function intersected(self $base, self $own): self
    {
        $type = self::admitsNull($base->type) ? $own->type : self::withoutNull($own->type);
        $baseShape = self::withoutNull(self::declared($base->type));
        $ownShape = self::withoutNull(self::declared($type));
        if ($baseShape instanceof ShapeType && $ownShape instanceof ShapeType) {
            $shape = ShapeType::intersected($baseShape, $ownShape);
            $type = match (true) {
                $type instanceof NullableType => new NullableType($shape, $type->optional),
                self::admitsNull($type) => new NullableType($shape, optional: false),
                default => $shape,
            };
        }
        if ($base->mayBeLeftOut() && $own->mayBeLeftOut()) {
            return new self($own->name, $type, $own->optional);
        }

        // Required: a `?T` either side wrote is a key left out when null, which it no longer may be.
        return new self($own->name, $type instanceof NullableType && $type->optional ? $type->inner : $type, false);
    }

    /**
     * The type a key's value is, its `?` aside: `?T`'s `T`.
     */
    private static function declared(ParsedType $type): ParsedType
    {
        return $type instanceof NullableType && $type->optional ? $type->inner : $type;
    }

    /**
     * The type without the null it admits: `T|null`'s `T`, a union's other members.
     */
    private static function withoutNull(ParsedType $type): ParsedType
    {
        if ($type instanceof NullableType && !$type->optional) {
            return $type->inner;
        }
        if (!$type instanceof UnionType) {
            return $type;
        }
        $members = array_values(array_filter($type->types, static fn (ParsedType $member): bool => !$member instanceof ScalarType || 'null' !== $member->base()));

        return 1 === \count($members) ? $members[0] : new UnionType($members);
    }
}
