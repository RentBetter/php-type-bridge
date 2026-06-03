<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter\Builtin;

use PTGS\TypeBridge\Attribute\AsTypeBridgeEmitter;
use PTGS\TypeBridge\Emitter\EmitContext;
use PTGS\TypeBridge\Emitter\EmittedBlock;
use PTGS\TypeBridge\Emitter\EmittedType;
use PTGS\TypeBridge\Emitter\EmitMode;
use PTGS\TypeBridge\Emitter\TypeEmitter;
use ReflectionClass;
use ReflectionEnum;

/**
 * Built-in convention: a string-backed enum referenced via `value-of<Enum>` emits
 * as a union of its backing values. Referenced — only the enums actually touched
 * by another declaration are emitted.
 */
#[AsTypeBridgeEmitter('value-of', mode: EmitMode::Referenced)]
final class ValueOfEnumEmitter implements TypeEmitter
{
    public function claims(ReflectionClass $class): bool
    {
        $name = $class->getName();
        if (!enum_exists($name)) {
            return false;
        }

        $enum = new ReflectionEnum($name);
        $backingType = $enum->getBackingType();

        return $enum->isBacked() && null !== $backingType && 'string' === $backingType->getName();
    }

    public function emit(ReflectionClass $class, EmitContext $context): EmittedType
    {
        $values = array_map(
            static fn(string $value): string => "'" . $value . "'",
            $context->enumResolver->resolve($class->getName()),
        );

        $code = \sprintf(
            'export type %s = %s;',
            $context->names->enumName($class->getName()),
            implode(' | ', $values),
        );

        return new EmittedType($context->domain, [new EmittedBlock(10, '// Enums', $code)]);
    }
}
