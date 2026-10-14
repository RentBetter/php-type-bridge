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
     */
    public function mayBeLeftOut(): bool
    {
        return $this->optional || ($this->type instanceof NullableType && $this->type->optional);
    }
}
