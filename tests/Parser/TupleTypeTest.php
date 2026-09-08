<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Parser;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Parser\ScalarType;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Parser\TupleType;
use PTGS\TypeBridge\Shape\ShapeRenderer;
use RuntimeException;

/**
 * `array{float, float}` is PHPStan's positional tuple, distinct from a keyed shape and a
 * different TypeScript type: `[number, number]`, not an object.
 */
final class TupleTypeTest extends TestCase
{
    public function test_it_parses_a_positional_tuple(): void
    {
        $parsed = (new PhpDocShapeParser())->parse('array{float, float}');

        self::assertInstanceOf(TupleType::class, $parsed);
        self::assertCount(2, $parsed->elements);
        self::assertInstanceOf(ScalarType::class, $parsed->elements[0]);
    }

    public function test_a_keyed_shape_is_still_a_shape(): void
    {
        self::assertInstanceOf(ShapeType::class, (new PhpDocShapeParser())->parse('array{a: int, b: string}'));
    }

    public function test_it_parses_a_tuple_nested_in_a_shape(): void
    {
        $parsed = (new PhpDocShapeParser())->parse('array{latLng?: array{float, float}}');

        self::assertInstanceOf(ShapeType::class, $parsed);
        $latLng = $parsed->fields[0]->type;
        self::assertInstanceOf(TupleType::class, $latLng);
    }

    public function test_it_parses_a_tuple_of_lists(): void
    {
        $parsed = (new PhpDocShapeParser())->parse('array{string, string, string[]}');

        self::assertInstanceOf(TupleType::class, $parsed);
        self::assertCount(3, $parsed->elements);
    }

    public function test_it_survives_a_render_round_trip(): void
    {
        $shape = 'array{latLng?: array{float, float}}';

        self::assertSame($shape, (new ShapeRenderer())->render((new PhpDocShapeParser())->parse($shape)));
    }

    /** Half-keyed has no TypeScript equivalent, so it is rejected rather than guessed at. */
    public function test_mixing_keyed_and_positional_is_an_error(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('all keyed or all positional');

        (new PhpDocShapeParser())->parse('array{a: int, string}');
    }

    public function test_a_tuple_may_not_be_an_intersection_right_hand_side(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must be a keyed shape, not a tuple');

        (new PhpDocShapeParser())->parse('Base & array{int, int}');
    }
}
