<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Collector;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;

/**
 * The PHPStan syntax a real codebase writes, end to end: each shape emits the TypeScript that
 * describes the same JSON. Every one of these used to stop generation for the whole codebase.
 */
final class PhpDocTypeCollectorGrammarTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/GrammarFixtures';

    private static string $output;

    public static function setUpBeforeClass(): void
    {
        $responseCollector = new ResponseClassCollector();
        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory(self::SRC);

        self::$output = (new TypeScriptEmitter(
            enumResolver: $enumResolver,
            domainMapper: new DomainMapper('/tmp/type-bridge-output'),
        ))->emit(
            (new PhpDocTypeCollector())->collect(self::SRC),
            $responseCollector->collect(self::SRC),
            (new EndpointContractCollector())->collect(self::SRC, $responseCollector->collectIndex(self::SRC)),
        )['Catalogue'];
    }

    /**
     * An enum's serialised form: the unsealed shared envelope, and a status's own keys on top.
     * Both keep an index signature, so the keys each enum adds are allowed.
     */
    public function test_an_unsealed_envelope_and_a_status_extending_it(): void
    {
        self::assertStringContainsString(
            "export interface ApiEnumData {\n  id: string;\n  name: string;\n  theme?: Theme | ThemeState;\n  [key: string]: unknown;\n}",
            self::$output,
        );
        self::assertStringContainsString(
            "export interface StatusData extends ApiEnumData {\n  ok: boolean;\n  value: number;\n  [key: string]: unknown;\n}",
            self::$output,
        );
    }

    public function test_a_constant_wildcard_is_the_values_of_the_constants_it_matches(): void
    {
        self::assertStringContainsString("export type ResultAction = 'approve' | 'reject';", self::$output);
        self::assertStringContainsString('export type Retry = 3;', self::$output);
    }

    public function test_value_of_a_constant_wildcard_in_a_list_is_a_list_of_those_values(): void
    {
        self::assertStringContainsString("manualModes?: ('simple' | 'full')[];", self::$output);
    }

    public function test_a_union_of_shapes_in_a_field(): void
    {
        self::assertStringContainsString('tooltip?: { text: string } | { md: string };', self::$output);
    }

    public function test_a_refined_int_is_a_number(): void
    {
        self::assertStringContainsString("export interface LengthHints {\n  good?: number;\n  great?: number;\n}", self::$output);
    }

    public function test_a_list_of_a_union_lists_the_union(): void
    {
        self::assertStringContainsString('children: (FormData | QuestionData)[];', self::$output);
    }

    public function test_a_class_string_key_is_a_string_key(): void
    {
        self::assertStringContainsString('export type Reviewers = Record<string, QuestionData>;', self::$output);
    }

    public function test_scalar_and_array_key(): void
    {
        self::assertStringContainsString('export type ScalarValue = string | number | boolean | null;', self::$output);
        // json_encode writes an array-key array as a list or an object, depending on its keys.
        self::assertStringContainsString('export type ScalarValues = Record<string, ScalarValue> | ScalarValue[];', self::$output);
    }

    public function test_an_unsealed_tuple_has_a_rest_element(): void
    {
        self::assertStringContainsString('export type Row = [number, string, ...unknown[]];', self::$output);
    }
}
