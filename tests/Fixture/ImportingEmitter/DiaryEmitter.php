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

/** Imports a `Date` into Notes, which declares one of its own. */
#[AsTypeBridgeEmitter('diary', mode: EmitMode::Discovered)]
final class DiaryEmitter implements TypeEmitter
{
    public function claims(ReflectionClass $class): bool
    {
        return $class->implementsInterface(Dated::class);
    }

    public function emit(ReflectionClass $class, EmitContext $context): EmittedType
    {
        return new EmittedType(
            domain: 'Notes',
            blocks: [new EmittedBlock(10, null, 'export interface Entry { on: Date; }', 'Entry', ['Entry'])],
            imports: [new EmitImport('Shared', 'Date')],
        );
    }
}
