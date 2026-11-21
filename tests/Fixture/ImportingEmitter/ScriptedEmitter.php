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

/** Writes what a test scripts: into a module, its declarations and its imports. */
#[AsTypeBridgeEmitter('scripted', mode: EmitMode::Discovered)]
final class ScriptedEmitter implements TypeEmitter
{
    public static string $domain = '';

    /** @var array<string, list<string>> each block's code, and the names it states it declares */
    public static array $code = [];

    /** @var list<array{string, string}> each import's module and name */
    public static array $imports = [];

    public function claims(ReflectionClass $class): bool
    {
        return $class->implementsInterface(Scripted::class);
    }

    public function emit(ReflectionClass $class, EmitContext $context): EmittedType
    {
        return new EmittedType(
            domain: self::$domain,
            blocks: array_map(static fn (string $code, array $declares): EmittedBlock => new EmittedBlock(10, null, $code, $code, $declares), array_keys(self::$code), self::$code),
            imports: array_map(static fn (array $import): EmitImport => new EmitImport($import[0], $import[1]), self::$imports),
        );
    }
}
