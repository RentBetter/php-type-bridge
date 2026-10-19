<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Support\TypeScriptTypes;

/**
 * Whether a TypeScript type a project's config gives an alias admits null: `null` among the members
 * of its union, read outside brackets and quotes — an arrow's `=>` closing nothing.
 */
final class TypeScriptTypesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function types(): iterable
    {
        yield 'null in the union' => ['string | null', true];
        yield 'null first' => ['null|number', true];
        yield 'no null' => ['string', false];
        yield 'null inside a generic' => ['Array<string | null>', false];
        yield 'null inside an object' => ['{ a: string | null }', false];
        yield 'null in a string' => ["'a | null'", false];
        yield 'a function, or null' => ['(() => void) | null', true];
        yield 'a function taking one, or null' => ['((a: string) => string) | null', true];
        yield 'a function returning null' => ['() => null', false];
        yield 'a generic over a function, or null' => ['Promise<(x: number) => void> | null', true];
    }

    #[DataProvider('types')]
    public function testItAdmitsNullWhereItsUnionHasOne(string $type, bool $admits): void
    {
        self::assertSame($admits, TypeScriptTypes::admitsNull($type));
    }
}
