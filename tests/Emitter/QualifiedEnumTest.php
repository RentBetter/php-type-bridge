<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;

/**
 * An enum written whole, leading `\` and all, is the enum its short name is: declared once, in the
 * module it is in, and imported from there.
 */
final class QualifiedEnumTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/QualifiedEnumFixtures';

    /** @var array<string, string> */
    private static array $output;

    public static function setUpBeforeClass(): void
    {
        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory(self::SRC);

        self::$output = (new TypeScriptEmitter(
            enumResolver: $enumResolver,
            domainMapper: new DomainMapper('/tmp/type-bridge-output'),
        ))->emit((new PhpDocTypeCollector())->collect(self::SRC));
    }

    public function test_the_enum_is_declared_once_in_its_own_module(): void
    {
        self::assertSame(['Notes', 'Things'], array_keys(self::$output));
        self::assertSame(1, substr_count(self::$output['Notes'], "export type Status = 'open' | 'closed';"));
        self::assertStringContainsString("export interface NoteView {\n  status: Status;\n  other: Status;\n}", self::$output['Notes']);
        self::assertStringNotContainsString('import', self::$output['Notes']);
    }

    public function test_another_module_imports_it_from_there(): void
    {
        self::assertStringContainsString("import type { Status } from '../Notes/genTypes';", self::$output['Things']);
    }
}
