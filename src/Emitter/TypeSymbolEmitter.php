<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

use ReflectionClass;

/**
 * An emitter that publishes the TypeScript type it writes for a class it claims, so a shape
 * that names the class — `status: TaskStatus` — can point at it.
 *
 * The counterpart of {@see EnumIdSymbolEmitter} for the class itself rather than its ids: only
 * the emitter knows what it calls the type and which module it lands in.
 */
interface TypeSymbolEmitter
{
    /**
     * @param ReflectionClass<object> $class
     */
    public function typeSymbol(ReflectionClass $class): EmitImport;
}
