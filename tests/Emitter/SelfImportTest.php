<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;

/**
 * A class can declare what it serialises to by importing a type as its `_self` —
 * `@phpstan-import-type MoneyShape from MoneyNormalizer as _self` — rather than restating it. A
 * shape that names the class is then that type, and the class emits nothing under its own name.
 */
final class SelfImportTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/SelfImportFixtures';

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

    public function test_a_shape_naming_the_class_is_the_imported_type(): void
    {
        self::assertStringContainsString("export interface Total {\n  total: MoneyShape;\n}", self::$output['Reports']);
        self::assertStringContainsString("import type { MoneyShape } from '../Http/genTypes';", self::$output['Reports']);
    }

    /** The class's own import is nearer than the shape of an interface it implements. */
    public function test_the_import_wins_over_an_interfaces_shape(): void
    {
        self::assertStringNotContainsString('MoneyLike', self::$output['Reports']);
    }

    public function test_the_class_emits_nothing_under_its_own_name(): void
    {
        foreach (self::$output as $module) {
            self::assertDoesNotMatchRegularExpression('/export (type|interface) Money\b/', $module);
        }
    }
}
