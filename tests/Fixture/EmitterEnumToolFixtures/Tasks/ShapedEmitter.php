<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\EmitterEnumToolFixtures\Tasks;

use PTGS\TypeBridge\Attribute\AsTypeBridgeEmitter;
use PTGS\TypeBridge\Emitter\EmitContext;
use PTGS\TypeBridge\Emitter\EmitImport;
use PTGS\TypeBridge\Emitter\EmitMode;
use PTGS\TypeBridge\Emitter\EmittedBlock;
use PTGS\TypeBridge\Emitter\EmittedType;
use PTGS\TypeBridge\Emitter\TypeEmitter;
use PTGS\TypeBridge\Emitter\TypeSymbolEmitter;
use ReflectionClass;

/** Writes `export interface {Enum} { id: string; }` for a Shaped enum, and publishes that type. */
#[AsTypeBridgeEmitter('shaped', priority: 10, mode: EmitMode::Discovered)]
final class ShapedEmitter implements TypeEmitter, TypeSymbolEmitter
{
    public function claims(ReflectionClass $class): bool
    {
        return $class->isEnum() && $class->implementsInterface(Shaped::class);
    }

    public function emit(ReflectionClass $class, EmitContext $context): EmittedType
    {
        return new EmittedType(
            domain: 'Tasks',
            blocks: [new EmittedBlock(10, null, \sprintf('export interface %s { id: string; }', $class->getShortName()), $class->getShortName())],
        );
    }

    public function typeSymbol(ReflectionClass $class): EmitImport
    {
        return new EmitImport('Tasks', $class->getShortName());
    }
}
