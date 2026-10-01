<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;

/**
 * `#[ValueOfName]` names an enum's value union, and the enum's own name then goes to its `_self`
 * — `Currency = { code: CurrencyCode }` rather than `CurrencyData = { code: Currency }`.
 */
final class ValueOfNameTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/ValueOfNameFixtures';

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

    public function test_the_value_union_takes_the_name_given(): void
    {
        self::assertStringContainsString("export type CurrencyCode = 'AUD' | 'NZD';", self::$output['Money']);
    }

    public function test_the_shape_takes_the_enums_own_name(): void
    {
        self::assertStringContainsString("export interface Currency {\n  code: CurrencyCode;\n}", self::$output['Money']);
        self::assertStringNotContainsString('CurrencyData', self::$output['Money']);
    }

    public function test_another_module_imports_both_by_those_names(): void
    {
        self::assertStringContainsString("import type { Currency, CurrencyCode } from '../Money/genTypes';", self::$output['Billing']);
        self::assertStringContainsString("export interface Invoice {\n  currency: Currency;\n  code: CurrencyCode;\n}", self::$output['Billing']);
    }
}
