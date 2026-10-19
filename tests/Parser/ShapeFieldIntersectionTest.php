<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Parser;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Parser\ShapeType;

/**
 * The key `Base & Own` has where both declare it, as TypeScript reads the intersection: required
 * where either requires it, taking null only where both do.
 */
final class ShapeFieldIntersectionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool, bool}> base key, own key, may be left out, takes null
     */
    public static function keys(): iterable
    {
        yield 'both optional' => ['k?: string', 'k?: string', true, false];
        yield 'the base requires it' => ['k: string', 'k?: string', false, false];
        yield 'the own one requires it' => ['k?: string', 'k: string', false, false];
        yield 'null on the own side only' => ['k?: string', 'k?: string|null', true, false];
        yield 'null on the base side only' => ['k?: string|null', 'k?: string', true, false];
        yield 'null on both' => ['k?: string|null', 'k?: string|null', true, true];
        yield 'a union with null on one side' => ['k: string', "k: 'a'|'b'|null", false, false];
        // `?string` is a key left out when null; where the base requires the key, it no longer may be.
        yield 'left out when null, the base requiring it' => ['k: string', 'k: ?string', false, false];
        yield 'left out when null, both' => ['k: ?string', 'k: ?string', true, false];
    }

    #[DataProvider('keys')]
    public function testItIsTheKeyTheIntersectionHas(string $base, string $own, bool $mayBeLeftOut, bool $takesNull): void
    {
        $key = ShapeField::intersected(self::key($base), self::key($own));

        self::assertSame($mayBeLeftOut, $key->mayBeLeftOut());
        self::assertSame($takesNull, ShapeField::admitsNull($key->type));
    }

    public function testAShapeHasEveryKeyOfEitherSide(): void
    {
        $shape = ShapeType::intersected(self::shape('array{a: string, k?: string}'), self::shape('array{k: string, b?: int}'));

        self::assertSame(['a', 'k', 'b'], array_map(static fn(ShapeField $key): string => $key->name, $shape->fields));
        self::assertFalse($shape->fields[1]->mayBeLeftOut());
    }

    private static function key(string $key): ShapeField
    {
        return self::shape('array{' . $key . '}')->fields[0];
    }

    private static function shape(string $type): ShapeType
    {
        $shape = (new PhpDocShapeParser())->parse($type);
        self::assertInstanceOf(ShapeType::class, $shape);

        return $shape;
    }
}
