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
     * Whether a type admits null as a value sent: `T|null`, or a union with a null among several
     * members (`'open'|'closed'|null`, which parses as a union, not a nullable). `?T` does not: it
     * is a key left out when null, and a project's preserveNull rule holds every nullable key to
     * `?T` but the ones whose null means something.
     */
    public static function admitsNull(ParsedType $type): bool
    {
        if ($type instanceof NullableType) {
            return !$type->optional;
        }
        if (!$type instanceof UnionType) {
            return false;
        }
        foreach ($type->types as $member) {
            if ($member instanceof ScalarType && 'null' === $member->base()) {
                return true;
            }
        }

        return false;
    }
}
