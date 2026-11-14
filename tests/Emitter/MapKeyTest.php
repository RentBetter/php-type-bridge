<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;

/**
 * A map's key wherever it is set: PHP keys an array by the ints and strings it makes of the key,
 * so one bound to a template param, or named by an alias, is keyed as the parser keys one written
 * out — by any key where it names one no array has, or every one an array has.
 */
final class MapKeyTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/MapKeyFixtures';

    public function test_a_key_bound_to_a_template_param_is_keyed_as_one_written_out(): void
    {
        $rows = self::emit()['Rows'];

        self::assertStringContainsString("export interface MixedRowsResponse {\n  rows: Record<string, string> | string[];\n}", $rows);
        self::assertStringContainsString("export interface AnyRowsResponse {\n  rows: Record<string, unknown> | unknown[];\n}", $rows);
        self::assertStringContainsString("export interface NullableRowsResponse {\n  rows: Record<string, string> | string[];\n}", $rows);
        self::assertStringContainsString("export interface EitherRowsResponse {\n  rows: Record<string, string> | string[];\n}", $rows);
        self::assertStringContainsString("export interface IntRowsResponse {\n  rows: Record<number, string>;\n}", $rows);
        self::assertStringContainsString("export interface AliasRowsResponse {\n  rows: Record<string, string> | string[];\n}", $rows);
        self::assertStringContainsString("export interface CodeRowsResponse {\n  rows: Record<Code, string>;\n}", $rows);
        // Bound to an intersection, which is read whole in the file that writes it.
        self::assertStringContainsString("export interface ExtendedRowsResponse {\n  rows: Record<number, RowKeys & { extra: Record<string, string> | string[] }>;\n}", $rows);
    }

    public function test_an_inherited_key_is_read_in_the_module_of_the_file_that_writes_it(): void
    {
        // The base's aliases, of anything, are its module's: its own, and one another class of the
        // module declares, which this module has neither of.
        self::assertStringContainsString("export interface KeyedRowsResponse {\n  byBase: Record<string, string> | string[];\n  byModule: Record<string, string> | string[];\n}", self::emit()['Rows']);
    }

    public function test_a_key_an_alias_names_is_keyed_as_the_alias_is(): void
    {
        // Of a key no array has, or any key, it is any key; of one an array has, the alias.
        $rows = self::emit()['Rows'];

        self::assertStringContainsString(
            "export interface RowKeys {\n"
            . "  byMaybe: Record<string, string> | string[];\n"
            . "  byAny: Record<string, string> | string[];\n"
            . "  byCode: Record<Code, number>;\n"
            . "  byLoose: Record<string, string> | string[];\n"
            . "  byAnything: Record<string, string> | string[];\n"
            . "  byMaybeOrX: Record<string, string> | string[];\n"
            . "  byArrayKey: Record<string, string> | string[];\n"
            . "  byIntKey: Record<IntKey, string>;\n"
            . '}',
            $rows,
        );
    }

    /**
     * @return array<string, string>
     */
    private static function emit(): array
    {
        $responses = new ResponseClassCollector();
        $enums = new EnumResolver();
        $enums->scanDirectory(self::SRC);

        return (new TypeScriptEmitter(enumResolver: $enums, domainMapper: new DomainMapper('/tmp/type-bridge-output')))->emit(
            (new PhpDocTypeCollector())->collect(self::SRC),
            $responses->collect(self::SRC),
            (new EndpointContractCollector())->collect(self::SRC, $responses->collectIndex(self::SRC)),
        );
    }
}
