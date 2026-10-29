<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Support\TypeScriptTypes;

/**
 * Whether a TypeScript type a project's config gives an alias admits null, as `null extends T` holds:
 * `null` among the members of its union, read outside brackets and quotes — an arrow's `=>` closing
 * nothing — or another of the config's aliases that does. Null where the text alone can't say: a type
 * TypeBridge would have to evaluate, as tsc does, to know.
 */
final class TypeScriptTypesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool|null}>
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
        // `null extends T` holds for these too, as AsQuery<T> reads them.
        yield 'unknown' => ['unknown', true];
        yield 'any' => ['any', true];
        yield 'a union in brackets' => ['(string | null)', true];
        yield 'a list of nulls, in brackets' => ['(string | null)[]', false];
        yield 'unknown inside a generic' => ['Array<unknown>', false];
        yield 'a literal' => ["'draft' | 2 | -1 | true | `a${string}`", false];
        yield 'an object, a list, a tuple' => ['{ a: null } | null[] | [null] | readonly string[]', false];
        yield 'a function returning a union' => ['() => string | null', false];
        yield 'a constructor' => ['new () => null', false];
        yield 'a key of a type' => ['keyof Window', false];
        yield 'an intersection with a brand' => ["string & { readonly __brand: 'Id' }", false];
        yield 'an intersection of two that do' => ['unknown & (null | string)', true];
        yield 'undefined' => ['undefined | void | never', false];
        yield 'a built-in that never does' => ['Record<string, null> | Date | Promise<null> | NonNullable<string | null>', false];
        // What a generic, a conditional, an indexed access or a typeof makes is tsc's to work out.
        yield 'a generic it cannot see' => ['Nullable<string>', null];
        yield 'a conditional' => ['string extends number ? null : string', null];
        yield 'an indexed access' => ["Window['name']", null];
        yield 'a typeof' => ['typeof window', null];
        yield 'one member it cannot read, beside one that does' => ['Nullable<string> | null', true];
        yield 'one member it cannot read, beside none that does' => ['Nullable<string> | string', null];
        yield 'a conditional whose last branch has a null in its union' => ['string extends string ? string : number | null', null];
        yield 'an intersection with a part it cannot read' => ['Nullable<string> & unknown', null];
        yield 'a quote escaped in a literal' => ["'it\\'s' | null", true];
    }

    #[DataProvider('types')]
    public function testItAdmitsNullWhereItsUnionHasOne(string $type, ?bool $admits): void
    {
        self::assertSame($admits, TypeScriptTypes::admitsNull($type));
    }

    public function testAnotherOfTheConfigsAliasesIsReadAsItIsDeclared(): void
    {
        $aliases = ['MaybeId' => 'Id | null', 'Id' => 'string', 'Loop' => 'Loop | string', 'Opaque' => 'Nullable<string>'];

        self::assertTrue(TypeScriptTypes::admitsNull('MaybeId', $aliases));
        self::assertFalse(TypeScriptTypes::admitsNull('Id', $aliases));
        self::assertNull(TypeScriptTypes::admitsNull('Loop', $aliases), 'An alias that names itself says nothing');
        self::assertNull(TypeScriptTypes::admitsNull('Opaque', $aliases));
        self::assertNull(TypeScriptTypes::admitsNull('Unknown', $aliases), 'A name the config does not declare');
    }
}
