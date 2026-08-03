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
    public function __construct(
        public ParsedType $key,
        public ParsedType $value,
    ) {}
}
