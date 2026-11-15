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

/** Imports `Stamp` from the root module into Notes, which imports it from there too. */
#[AsTypeBridgeEmitter('stamp', mode: EmitMode::Discovered)]
final class StampEmitter implements TypeEmitter
{
    public function claims(ReflectionClass $class): bool
    {
        return $class->implementsInterface(Stamped::class);
    }

    public function emit(ReflectionClass $class, EmitContext $context): EmittedType
    {
        return new EmittedType(
            domain: 'Notes',
            blocks: [new EmittedBlock(10, null, 'export interface Stamped { at: Stamp; }', 'Stamped')],
            imports: [new EmitImport('', 'Stamp')],
        );
    }
}
