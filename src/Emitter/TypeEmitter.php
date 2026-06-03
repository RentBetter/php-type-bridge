<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

use ReflectionClass;

/**
 * Renders a single PHP class into TypeScript under a named convention.
 *
 * Registered via #[AsTypeBridgeEmitter]. The orchestrator routes each class to
 * exactly one owning emitter (by claims(), ties broken by priority) and assembles
 * the returned {@see EmittedType}s into per-domain modules.
 */
interface TypeEmitter
{
    /**
     * @param ReflectionClass<object> $class
     */
    public function claims(ReflectionClass $class): bool;

    /**
     * @param ReflectionClass<object> $class
     */
    public function emit(ReflectionClass $class, EmitContext $context): EmittedType;
}
