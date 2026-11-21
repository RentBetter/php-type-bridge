<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ShadowingEmitter;

use PTGS\TypeBridge\Attribute\AsTypeBridgeEmitter;
use PTGS\TypeBridge\Emitter\EmitContext;
use PTGS\TypeBridge\Emitter\EmitMode;
use PTGS\TypeBridge\Emitter\EmittedBlock;
use PTGS\TypeBridge\Emitter\EmittedType;
use PTGS\TypeBridge\Emitter\TypeEmitter;
use ReflectionClass;

/** Declares an interface of TypeScript's global `Record`'s name in a module whose code uses that global. */
#[AsTypeBridgeEmitter('record', mode: EmitMode::Discovered)]
final class RecordEmitter implements TypeEmitter
{
    public function claims(ReflectionClass $class): bool
    {
        return $class->implementsInterface(Ledgered::class);
    }

    public function emit(ReflectionClass $class, EmitContext $context): EmittedType
    {
        return new EmittedType(
            domain: 'Ledgers',
            blocks: [
                new EmittedBlock(10, null, '/** A row. */ export interface Record { id: string; }', 'Record', ['Record']),
                new EmittedBlock(10, null, 'export type Totals = Record<string, number>;', 'Totals', ['Totals']),
            ],
        );
    }
}
