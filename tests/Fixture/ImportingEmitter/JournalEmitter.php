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

/** Imports a `Date` into Journals, which imports Calendar's under another name. */
#[AsTypeBridgeEmitter('journal', mode: EmitMode::Discovered)]
final class JournalEmitter implements TypeEmitter
{
    public function claims(ReflectionClass $class): bool
    {
        return $class->implementsInterface(Journaled::class);
    }

    public function emit(ReflectionClass $class, EmitContext $context): EmittedType
    {
        return new EmittedType(
            domain: 'Journals',
            blocks: [new EmittedBlock(10, null, 'export interface Page { on: Date; }', 'Page', ['Page'])],
            imports: [new EmitImport('Shared', 'Date')],
        );
    }
}
