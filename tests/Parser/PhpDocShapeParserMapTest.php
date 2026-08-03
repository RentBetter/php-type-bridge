<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Parser;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Parser\ListType;
use PTGS\TypeBridge\Parser\MapType;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Parser\ScalarType;
use PTGS\TypeBridge\Parser\ShapeType;
use RuntimeException;

/**
 * `array<K, V>` — maps with an open key set.
 *
 * The case that motivated this: an FX table keyed by currency pair then by day,
 * `array<string, array<string, string>>`. Before, `array` fell through to a bare
 * name reference and the following `<` broke the field loop, so a whole file of
 * types could not be generated because of one nested map.
 */
final class PhpDocShapeParserMapTest extends TestCase
{
    public function test_it_parses_a_simple_map(): void
    {
        $type = (new PhpDocShapeParser())->parse('array<string, int>');

        self::assertInstanceOf(MapType::class, $type);
        self::assertInstanceOf(ScalarType::class, $type->key);
        self::assertSame('string', $type->key->type);
        self::assertInstanceOf(ScalarType::class, $type->value);
        self::assertSame('int', $type->value->type);
    }

    public function test_it_parses_a_map_nested_in_a_map(): void
    {
        // The shape that used to abort generation entirely.
        $type = (new PhpDocShapeParser())->parse('array<string, array<string, string>>');

        self::assertInstanceOf(MapType::class, $type);
        self::assertInstanceOf(MapType::class, $type->value);
        self::assertInstanceOf(ScalarType::class, $type->value->value);
        self::assertSame('string', $type->value->value->type);
    }

    public function test_it_parses_a_map_of_shapes(): void
    {
        $type = (new PhpDocShapeParser())->parse('array<string, array{date: string, rate: string}>');

        self::assertInstanceOf(MapType::class, $type);
        self::assertInstanceOf(ShapeType::class, $type->value);
        self::assertCount(2, $type->value->fields);
    }

    public function test_it_parses_a_map_as_a_shape_field_alongside_other_fields(): void
    {
        $shape = (new PhpDocShapeParser())->parse(
            'array{reportingCurrency: string, ratesUsed: array<string, array<string, string>>, '
            . 'missingRates: list<array{date: string, currency: string}>}',
        );

        self::assertInstanceOf(ShapeType::class, $shape);
        $fields = [];
        foreach ($shape->fields as $field) {
            $fields[$field->name] = $field->type;
        }

        self::assertInstanceOf(ScalarType::class, $fields['reportingCurrency']);
        self::assertInstanceOf(MapType::class, $fields['ratesUsed']);
        // The field AFTER the map must still parse — the original bug swallowed
        // the rest of the shape once it hit the map.
        self::assertInstanceOf(ListType::class, $fields['missingRates']);
    }

    public function test_it_tolerates_whitespace_around_the_separator(): void
    {
        $type = (new PhpDocShapeParser())->parse('array<  string ,  int  >');

        self::assertInstanceOf(MapType::class, $type);
        self::assertInstanceOf(ScalarType::class, $type->value);
        self::assertSame('int', $type->value->type);
    }

    public function test_a_shape_is_not_mistaken_for_a_map(): void
    {
        // `array{` must keep winning over `array<`.
        $type = (new PhpDocShapeParser())->parse('array{a: string}');

        self::assertInstanceOf(ShapeType::class, $type);
    }

    public function test_it_rejects_the_single_argument_form(): void
    {
        // PHPStan reads `array<V>` as an INTEGER-keyed list. Emitting
        // Record<string, V> for it would produce a type that lies about the
        // data, so this fails loudly and points at list<V>.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/write list<V> instead|key and a value type/');

        (new PhpDocShapeParser())->parse('array<string>');
    }
}
