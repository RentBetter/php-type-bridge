<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

use ReflectionClass;

/**
 * Optional companion to {@see TypeEmitter} for emitters that also produce a
 * single shared module (e.g. base interfaces and catalogues) from the full set
 * of classes they own. Called once per emit run; the returned {@see EmittedType}
 * typically targets the root common module (domain = '').
 */
interface CommonModuleEmitter
{
    /**
     * @param list<ReflectionClass<object>> $classes every class this emitter owns
     */
    public function emitCommon(array $classes, EmitContext $context): EmittedType;
}
