<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Enum;

use Attribute;

/**
 * Declares where an enum case's id literal comes from, for emitters that opt into
 * id resolution (e.g. an API-enum convention).
 *
 * Exactly one strategy applies, in precedence order:
 *  - $method: a public, no-arg, `: string` instance method called per case;
 *  - $map: a [class-string, staticMethod] pair, the static method receiving the case;
 *  - $source: a pure-reflection {@see EnumIdSourceMode} (the default).
 *
 * Only the $method / $map strategies execute code; they are used solely by opt-in
 * emitters, never by TypeBridge core.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class EnumIdSource
{
    /**
     * @param non-empty-string|null                   $method
     * @param array{class-string, non-empty-string}|null $map
     */
    public function __construct(
        public ?string $method = null,
        public ?array $map = null,
        public EnumIdSourceMode $source = EnumIdSourceMode::BackingValue,
    ) {}
}
