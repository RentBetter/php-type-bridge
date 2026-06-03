<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\Discovered;

use PTGS\TypeBridge\Attribute\AsTypeBridgeEmitter;
use PTGS\TypeBridge\Emitter\CommonModuleEmitter;
use PTGS\TypeBridge\Emitter\EmitContext;
use PTGS\TypeBridge\Emitter\EmittedBlock;
use PTGS\TypeBridge\Emitter\EmittedType;
use PTGS\TypeBridge\Emitter\EmitImport;
use PTGS\TypeBridge\Emitter\EmitMode;
use PTGS\TypeBridge\Emitter\TypeEmitter;
use ReflectionClass;

/**
 * Minimal Discovered-mode emitter for exercising emitDiscovered(): claims enums
 * implementing Marked, emits a banner-less Id union into a fixed domain importing
 * a shared Base from the root module, and contributes that Base via emitCommon.
 */
#[AsTypeBridgeEmitter('marked', priority: 10, mode: EmitMode::Discovered)]
final class MarkedEmitter implements TypeEmitter, CommonModuleEmitter
{
    public function claims(ReflectionClass $class): bool
    {
        return $class->isEnum() && $class->implementsInterface(Marked::class);
    }

    public function emit(ReflectionClass $class, EmitContext $context): EmittedType
    {
        $shortName = $class->getShortName();
        $fqcn = $class->getName();
        $names = array_map(
            static fn(\UnitEnum $case): string => "'" . $case->name . "'",
            $fqcn::cases(),
        );

        $code = \sprintf('export type %sId = %s;', $shortName, implode(' | ', $names));

        return new EmittedType(
            domain: 'Marked',
            blocks: [new EmittedBlock(10, null, $code)],
            imports: [new EmitImport('', 'Base')],
        );
    }

    public function emitCommon(array $classes, EmitContext $context): EmittedType
    {
        return new EmittedType('', [new EmittedBlock(10, null, "export interface Base {\n  id: string;\n}")], []);
    }
}
