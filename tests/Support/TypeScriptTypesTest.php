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
        yield 'a literal' => ['\'draft\' | 2 | -1 | true | `a${string}`', false];
        yield 'an object, a list, a tuple' => ['{ a: null } | null[] | [null] | readonly string[]', false];
        yield 'a function returning a union' => ['() => string | null', false];
        yield 'a constructor' => ['new () => null', false];
        yield 'a key of a type' => ['keyof Window', false];
        yield 'an intersection with a brand' => ["string & { readonly __brand: 'Id' }", false];
        yield 'an intersection of two that do' => ['unknown & (null | string)', true];
        yield 'any intersected, which is any' => ['any & string', true];
        yield 'undefined' => ['undefined | void | never', false];
        yield 'a built-in that never does' => ['Record<string, null> | Date | Promise<null> | NonNullable<string | null>', false];
        // What a generic, a conditional, an indexed access or a typeof makes is tsc's to work out.
        yield 'a generic it cannot see' => ['Nullable<string>', null];
        yield 'a conditional' => ['string extends number ? null : string', null];
        yield 'a conditional with a function in a branch' => ['X extends string ? () => void : null', null];
        yield 'a function returning a conditional' => ['() => X extends string ? null : string', false];
        yield 'an indexed access' => ["Window['name']", null];
        yield 'a typeof' => ['typeof window', null];
        yield 'one member it cannot read, beside one that does' => ['Nullable<string> | null', true];
        yield 'one member it cannot read, beside none that does' => ['Nullable<string> | string', null];
        yield 'a conditional whose last branch has a null in its union' => ['string extends string ? string : number | null', null];
        yield 'an intersection with a part it cannot read' => ['Nullable<string> & unknown', null];
        yield 'a quote escaped in a literal' => ["'it\\'s' | null", true];
        yield 'a backslash escaped at the end of a literal' => ["'a\\\\' | null", true];
    }

    #[DataProvider('types')]
    public function testItAdmitsNullWhereItsUnionHasOne(string $type, ?bool $admits): void
    {
        self::assertSame($admits, TypeScriptTypes::admitsNull($type));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function operands(): iterable
    {
        yield 'a name' => ['Note', false];
        yield 'a generic' => ['Record<string, A | B>', false];
        yield 'an object' => ['{ a: A | B }', false];
        yield 'a union' => ['A | B', true];
        yield 'an intersection' => ['A & { b: B }', true];
        yield 'a function' => ['() => void', true];
        yield 'a conditional' => ['A extends B ? C : D', true];
        yield 'a key of a type' => ['keyof Note', true];
        yield 'a bracketed union' => ['(A | B)', false];
    }

    #[DataProvider('operands')]
    public function testItSaysWhichTypesNeedBracketsToBeAnOperand(string $type, bool $needs): void
    {
        self::assertSame($needs, TypeScriptTypes::needsBrackets($type));
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

    public function test_pick_and_omit_never_admit_null(): void
    {
        // Each maps the keys it is given to an object: whatever it is given, never a null.
        self::assertFalse(TypeScriptTypes::admitsNull("Omit<NoteData, 'id'>"));
        self::assertFalse(TypeScriptTypes::admitsNull("Pick<NoteData, 'id' | 'title'>"));
    }

    public function test_a_key_is_what_its_members_are_read_through_aliases_and_out_of_brackets(): void
    {
        $aliases = ['Num' => 'number', 'Key' => '(Num) | string', 'Loop' => 'Loop | number'];

        self::assertSame('any', TypeScriptTypes::keyKind('Key', $aliases));
        self::assertSame('number', TypeScriptTypes::keyKind('Num', $aliases));
        self::assertSame('string', TypeScriptTypes::keyKind("\n  | string", $aliases), 'A union may lead with its bar');
        self::assertNull(TypeScriptTypes::keyKind("'a' | 'b'"), 'A literal is a key, not every string');
        self::assertNull(TypeScriptTypes::keyKind('(string | number) & (boolean | number)'), 'An intersection, not a union in brackets');
        self::assertNull(TypeScriptTypes::keyKind('Loop', $aliases), 'An alias that names itself says nothing');
    }

    public function test_the_names_a_type_uses_are_none_quoted_keyed_or_read_off_another(): void
    {
        self::assertSame(['Brand', 'Date', 'string', 'A'], TypeScriptTypes::namesIn("Brand<Date, \"Id\"> | { id: string; at?: Date } | A.B | 'x y'"));
        // A conditional's branches are names; an index signature's key and a param's are not.
        self::assertSame(['string', 'extends', 'Date', 'number'], TypeScriptTypes::namesIn('string extends string ? Date : number'));
        self::assertSame(['string', 'Date'], TypeScriptTypes::namesIn('{ [key: string]: Date }'));
        self::assertSame(['Date', 'void'], TypeScriptTypes::namesIn('(at: Date, readonly by?: Date) => void'));
        self::assertSame(['string', 'Foo'], TypeScriptTypes::namesIn("{\n  a: string\n  b: Foo\n}"), 'A key on a line of its own');
        self::assertSame(['T', 'extends', 'U', 'X', 'Y'], TypeScriptTypes::namesIn("T extends U ?\n  X\n  : Y"), 'A branch on a line of its own');
        self::assertSame(['Region', 'Locale'], TypeScriptTypes::namesIn('`${Region}-${Locale}`'), "A template literal's holes");
        // A key is one where an object's, an index signature's or a params list's is, alone.
        self::assertSame(['T', 'extends', 'X', 'Foo', 'Bar'], TypeScriptTypes::namesIn("T extends X ?\n  Foo :\n  Bar"), "A branch ending its line");
        self::assertSame(['Foo'], TypeScriptTypes::namesIn("{\n  key\n    : Foo }"), 'A key whose `:` is on the next line');
        self::assertSame(['Foo'], TypeScriptTypes::namesIn('{ get(): Foo }'), "A method's name");
        self::assertSame(['T', 'extends', 'X', 'Foo', 'Bar'], TypeScriptTypes::namesIn("{ a: T extends X ?\n  Foo :\n  Bar }"), 'A branch in an object');
        self::assertSame(['X', 'Y', 'Z'], TypeScriptTypes::namesIn("{\n  f: (a: X) => Y\n  b: Z\n}"), "An arrow's `>` closes nothing");
        self::assertSame(['X', 'Y'], TypeScriptTypes::namesIn('{ readonly a: X; readonly b?: Y }'), 'A readonly key');
    }

    public function test_a_types_json_schema_is_what_json_can_say_of_it(): void
    {
        $aliases = ['Id' => 'string'];

        self::assertSame(['type' => 'string'], TypeScriptTypes::jsonSchema('Id | null', $aliases), 'A null is the reader\'s to say');
        self::assertSame(['type' => 'string'], TypeScriptTypes::jsonSchema("string | 'a'", $aliases), 'A literal of a type taken whole');
        self::assertSame(['type' => 'number'], TypeScriptTypes::jsonSchema('number | 1'), 'An int is a number');
        self::assertSame(['enum' => ['en', 'fr']], TypeScriptTypes::jsonSchema('\'en\' | "fr"'));
        self::assertSame(['enum' => [-1, 2, 1.5]], TypeScriptTypes::jsonSchema('-1 | 2 | 1.5'));
        self::assertSame(['anyOf' => [['type' => 'number'], ['enum' => [true]]]], TypeScriptTypes::jsonSchema('number | true'), 'A boolean literal is no number');
        self::assertSame(['enum' => [true, false]], TypeScriptTypes::jsonSchema('true | false'));
        self::assertNull(TypeScriptTypes::jsonSchema('string & {}'), 'A type JSON Schema has no word for');
        self::assertNull(TypeScriptTypes::jsonSchema("'a' | 'it\\'s'"), 'A literal with an escape in it');
        self::assertNull(TypeScriptTypes::jsonSchema("'a\\\\b'"), 'An escaped backslash, which is one');
        self::assertNull(TypeScriptTypes::jsonSchema('null'), 'Nothing but a null');
    }
}
