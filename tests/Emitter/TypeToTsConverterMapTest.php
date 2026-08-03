<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Emitter\ConversionScope;
use PTGS\TypeBridge\Emitter\EmittedNames;
use PTGS\TypeBridge\Emitter\SymbolRegistry;
use PTGS\TypeBridge\Emitter\TypeToTsConverter;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Config\TypeScriptNaming;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;

/**
 * `array<K, V>` renders as `Record<K, V>`.
 *
 * Parsing it is only half the job — an unrendered node would still abort
 * generation, just later and with a worse message.
 */
final class TypeToTsConverterMapTest extends TestCase
{
    public function test_it_renders_a_simple_map_as_a_record(): void
    {
        self::assertSame('Record<string, number>', $this->convert('array<string, int>'));
    }

    public function test_it_renders_a_nested_map(): void
    {
        // The FX table that motivated the feature.
        self::assertSame(
            'Record<string, Record<string, string>>',
            $this->convert('array<string, array<string, string>>'),
        );
    }

    public function test_it_renders_a_map_of_shapes(): void
    {
        self::assertSame(
            'Record<string, { date: string; rate: string }>',
            $this->convert('array<string, array{date: string, rate: string}>'),
        );
    }

    public function test_it_renders_a_map_inside_a_shape_without_swallowing_later_fields(): void
    {
        $ts = $this->convert(
            'array{reportingCurrency: string, ratesUsed: array<string, array<string, string>>, '
            . 'missingRates: list<array{date: string, currency: string}>}',
        );

        self::assertStringContainsString('ratesUsed: Record<string, Record<string, string>>', $ts);
        self::assertStringContainsString('missingRates: { date: string; currency: string }[]', $ts);
    }

    public function test_it_renders_a_list_of_maps(): void
    {
        self::assertSame('Record<string, string>[]', $this->convert('list<array<string, string>>'));
    }

    private function convert(string $phpDoc): string
    {
        $converter = new TypeToTsConverter(
            new EmittedNames(new TypeScriptNaming(), new EnumResolver()),
            new SymbolRegistry([]),
        );

        return $converter->convert(
            (new PhpDocShapeParser())->parse($phpDoc),
            new ConversionScope('Test'),
        );
    }
}
