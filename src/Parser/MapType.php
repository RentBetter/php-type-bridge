<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

/**
 * `array<K, V>` — a map with an open key set, emitted as TS `Record<K, V>`.
 *
 * Distinct from ShapeType, which has a known, fixed set of named fields. Reach
 * for this only when the keys genuinely are not knowable up front (a lookup
 * built from database rows, an FX table keyed by currency pair); a fixed set of
 * fields is better expressed as `array{...}` so consumers get real names.
 *
 * The single-argument `array<V>` form is deliberately NOT accepted. PHPStan
 * reads it as a list with integer keys, and silently treating it as a map keyed
 * by string would emit a type that lies about the data.
 */
final readonly class MapType extends ParsedType
{
    /** The key it is sent with ({@see self::keyOf()}), however the map is built: parsed, or a template param bound. */
    public ParsedType $key;

    public function __construct(
        ParsedType $key,
        public ParsedType $value,
    ) {
        $this->key = self::keyOf($key);
    }

    /**
     * The key a map is sent with. PHP keys an array by ints and strings alone, so a key named as
     * anything else — `mixed`, an object, a bool, a null — is whichever of them PHP makes of it: any
     * key; and so is one of an int and a string both, PHP's own `array-key`. A name, an enum's values
     * or a constant's are kept, and read where they are resolved.
     */
    public static function keyOf(ParsedType $key): ParsedType
    {
        if ($key instanceof ScalarType && 'array-key' === $key->type) {
            return $key;
        }

        return self::isKey($key) && !self::isAnyKey($key) ? $key : new ScalarType('array-key');
    }

    private static function isKey(ParsedType $type): bool
    {
        return match (true) {
            $type instanceof ScalarType => \in_array($type->base(), ['int', 'string', 'array-key', 'float', 'numeric'], true),
            $type instanceof LiteralType => \is_int($type->value) || \is_string($type->value),
            $type instanceof UnionType => array_all($type->types, self::isKey(...)),
            $type instanceof ScopedType => self::isKey($type->inner),
            default => $type instanceof NameRefType || $type instanceof ValueOfType || $type instanceof IdOfType || $type instanceof ClassConstantType,
        };
    }

    /**
     * Whether the key is every one an array has: `array-key`, or a union of an int's — a float is
     * keyed as the int it truncates to — and a string's.
     */
    private static function isAnyKey(ParsedType $type): bool
    {
        $bases = [];
        $types = [$type];
        while ([] !== $types) {
            $member = array_pop($types);
            if ($member instanceof UnionType) {
                array_push($types, ...$member->types);
            } elseif ($member instanceof ScopedType) {
                $types[] = $member->inner;
            } elseif ($member instanceof ScalarType) {
                $bases[$member->base()] = true;
            }
        }

        return isset($bases['array-key']) || ((isset($bases['int']) || isset($bases['float']) || isset($bases['numeric'])) && isset($bases['string']));
    }
}
