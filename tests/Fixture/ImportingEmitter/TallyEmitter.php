<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ImportingEmitter;

use PTGS\TypeBridge\Attribute\AsTypeBridgeEmitter;
use PTGS\TypeBridge\Emitter\EmitContext;
use PTGS\TypeBridge\Emitter\EmitImport;
use PTGS\TypeBridge\Emitter\EmitMode;
use PTGS\TypeBridge\Emitter\EmittedBlock;
use PTGS\TypeBridge\Emitter\EmittedType;
use PTGS\TypeBridge\Emitter\TypeEmitter;
use ReflectionClass;

/** Imports a type of TypeScript's global `Record`'s name into a module whose code uses that global. */
#[AsTypeBridgeEmitter('tally', mode: EmitMode::Discovered)]
final class TallyEmitter implements TypeEmitter
{
    public function claims(ReflectionClass $class): bool
    {
        return $class->implementsInterface(Tallied::class);
    }

    public function emit(ReflectionClass $class, EmitContext $context): EmittedType
    {
        return new EmittedType(
            domain: 'Tallies',
            blocks: [new EmittedBlock(10, null, 'export type Totals = Record<string, number>;', 'Totals')],
            imports: [new EmitImport('Shared', 'Record')],
        );
    }
}
