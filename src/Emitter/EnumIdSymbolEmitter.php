<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

use ReflectionClass;

/**
 * An emitter that publishes a TypeScript type naming an enum's stable string ids, so
 * `id-of<Enum>` in a shape can point at it.
 *
 * TypeBridge deliberately does not compute the ids itself. An id may be overridden or
 * derived at runtime, so only the emitter that already produces the union knows both
 * what it is called and which module it lands in — this returns that, and nothing is
 * emitted twice.
 */
interface EnumIdSymbolEmitter
{
    /**
     * @param ReflectionClass<object> $enum
     */
    public function idSymbol(ReflectionClass $enum): EmitImport;
}
