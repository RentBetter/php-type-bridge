<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Parser;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Parser\ClassConstantType;
use PTGS\TypeBridge\Parser\IntersectionType;
use PTGS\TypeBridge\Parser\ListType;
use PTGS\TypeBridge\Parser\MapType;
use PTGS\TypeBridge\Parser\NameRefType;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Parser\ScalarType;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Parser\TupleType;
use PTGS\TypeBridge\Parser\UnionType;

/**
 * PHPStan syntax a real codebase writes in its shapes, each spelled the way phpdoc-parser hands
 * it over (unions and intersections parenthesised, spaces around `|` and `&`). Every one of these
 * used to abort generation for the whole codebase, not just its own shape.
 */
final class PhpDocShapeParserGrammarTest extends TestCase
{
    public function test_a_keyword_with_arguments_is_no_bare_keyword(): void
    {
        // `iterable` alone is any array; `iterable<string>` is an array of strings, as PHPStan reads
        // it — keyed by anything, `iterable<mixed, string>` — and a shape holding one still parses.
        $shape = (new PhpDocShapeParser())->parse('array{tags?: iterable<string>, name: string}');

        self::assertInstanceOf(ShapeType::class, $shape);
        self::assertInstanceOf(MapType::class, $shape->fields[0]->type);
        self::assertInstanceOf(ScalarType::class, $shape->fields[0]->type->key);
        self::assertSame('array-key', $shape->fields[0]->type->key->type);
        self::assertInstanceOf(ScalarType::class, $shape->fields[0]->type->value);
        self::assertSame('string', $shape->fields[0]->type->value->type);
        self::assertInstanceOf(MapType::class, (new PhpDocShapeParser())->parse('iterable'));
    }

    public function test_an_iterable_with_its_key_is_an_array_of_that_key(): void
    {
        $map = (new PhpDocShapeParser())->parse('iterable<int, list<string>>');

        self::assertInstanceOf(MapType::class, $map);
        self::assertInstanceOf(ScalarType::class, $map->key);
        self::assertSame('int', $map->key->type);
        self::assertInstanceOf(ListType::class, $map->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function keysNoArrayHas(): iterable
    {
        yield 'an iterable of mixed keys' => ['iterable<mixed, string>'];
        yield 'an iterable of object keys' => ['iterable<object, string>'];
        yield 'an iterable of shape keys' => ['iterable<array{id: int}, string>'];
        yield 'an array of mixed keys' => ['array<mixed, string>'];
        yield 'an array of scalar keys' => ['array<scalar, string>'];
        yield 'an array of bool keys' => ['array<bool, string>'];
        yield 'an array of nullable keys' => ['array<int|null, string>'];
        yield 'an array of int or bool keys' => ['array<int|bool, string>'];
    }

    #[DataProvider('keysNoArrayHas')]
    public function test_a_key_no_array_is_keyed_by_is_the_key_the_array_has(string $input): void
    {
        // PHP keys an array by ints and strings alone, and TypeScript a Record by what it can
        // spell of them: what else a map names is any key, as `iterable<V>` is.
        $map = (new PhpDocShapeParser())->parse($input);

        self::assertInstanceOf(MapType::class, $map);
        self::assertInstanceOf(ScalarType::class, $map->key);
        self::assertSame('array-key', $map->key->type);
    }

    public function test_a_key_of_every_kind_an_array_has_is_any_key(): void
    {
        // An int and a string both: PHP's own `array-key`, which a JSON list is sent as too.
        foreach (['array<int|string, string>', 'iterable<string|int, string>', 'array<int|non-empty-string, string>'] as $input) {
            $map = (new PhpDocShapeParser())->parse($input);
            self::assertInstanceOf(MapType::class, $map);
            self::assertInstanceOf(ScalarType::class, $map->key, $input);
            self::assertSame('array-key', $map->key->type, $input);
        }
    }

    public function test_a_map_built_with_a_key_no_array_has_is_keyed_by_any_key(): void
    {
        // As a template param bound to one puts it in a base's `array<TKey, V>`.
        self::assertSame('array-key', self::scalarKey(new MapType(new ScalarType('mixed'), new ScalarType('string'))));
        self::assertSame('array-key', self::scalarKey(new MapType(new UnionType([new ScalarType('int'), new ScalarType('null')]), new ScalarType('string'))));
        self::assertSame('array-key', self::scalarKey(new MapType(new UnionType([new ScalarType('int'), new ScalarType('string')]), new ScalarType('string'))));
        self::assertSame('int', self::scalarKey(new MapType(new ScalarType('int'), new ScalarType('string'))));
    }

    public function test_a_key_an_array_is_keyed_by_is_kept(): void
    {
        $parser = new PhpDocShapeParser();

        foreach (['array<positive-int, string>' => 'positive-int', 'iterable<class-string, string>' => 'class-string', 'array<float, string>' => 'float'] as $input => $key) {
            $map = $parser->parse($input);
            self::assertInstanceOf(MapType::class, $map);
            self::assertInstanceOf(ScalarType::class, $map->key);
            self::assertSame($key, $map->key->type);
        }
        foreach (["array<'a' | 'b', int>", 'array<value-of<Status>, int>', 'array<Code, int>'] as $input) {
            $map = $parser->parse($input);
            self::assertInstanceOf(MapType::class, $map);
            self::assertNotInstanceOf(ScalarType::class, $map->key, $input);
        }
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function refinements(): iterable
    {
        yield 'positive-int' => ['positive-int', 'positive-int', 'int'];
        yield 'non-negative-int' => ['non-negative-int', 'non-negative-int', 'int'];
        yield 'an int range' => ['int<0, max>', 'int', 'int'];
        yield 'non-empty-string' => ['non-empty-string', 'non-empty-string', 'string'];
        yield 'class-string of a class' => ['class-string<ReviewerInterface>', 'class-string', 'string'];
        yield 'scalar' => ['scalar', 'scalar', 'scalar'];
        yield 'array-key' => ['array-key', 'array-key', 'array-key'];
    }

    #[DataProvider('refinements')]
    public function test_a_refinement_keeps_its_spelling_and_knows_what_it_refines(string $input, string $spelling, string $base): void
    {
        $type = (new PhpDocShapeParser())->parse($input);

        self::assertInstanceOf(ScalarType::class, $type);
        self::assertSame($spelling, $type->type);
        self::assertSame($base, $type->base());
    }

    public function test_a_longer_name_that_starts_with_a_keyword_is_still_a_name(): void
    {
        $type = (new PhpDocShapeParser())->parse('scalars');

        self::assertInstanceOf(NameRefType::class, $type);
        self::assertSame('scalars', $type->name);
    }

    public function test_non_empty_list_and_array_are_a_list_and_a_map(): void
    {
        $parser = new PhpDocShapeParser();

        self::assertInstanceOf(ListType::class, $parser->parse('non-empty-list<string>'));
        self::assertInstanceOf(MapType::class, $parser->parse('non-empty-array<string, int>'));
    }

    public function test_a_class_string_can_key_a_map(): void
    {
        $type = (new PhpDocShapeParser())->parse('array<class-string<ReviewerInterface>, ResultData>');

        self::assertInstanceOf(MapType::class, $type);
        self::assertInstanceOf(ScalarType::class, $type->key);
        self::assertSame('string', $type->key->base());
    }

    public function test_a_parenthesised_union_of_shapes_keeps_every_member(): void
    {
        $type = (new PhpDocShapeParser())->parse('(array{values: Dict} | array{form: string, values: Dict})');

        self::assertInstanceOf(UnionType::class, $type);
        self::assertCount(2, $type->types);
        self::assertContainsOnlyInstancesOf(ShapeType::class, $type->types);
    }

    public function test_a_union_of_shapes_parses_inside_a_field(): void
    {
        $type = (new PhpDocShapeParser())->parse('array{tooltip?: (array{text: string} | array{md: string})}');

        self::assertInstanceOf(ShapeType::class, $type);
        self::assertInstanceOf(UnionType::class, $type->fields[0]->type);
    }

    public function test_an_unsealed_shape_keeps_its_fields_and_says_so(): void
    {
        $type = (new PhpDocShapeParser())->parse('array{id: string, name: string, theme?: (Theme | ThemeState), ...}');

        self::assertInstanceOf(ShapeType::class, $type);
        self::assertTrue($type->unsealed);
        self::assertSame(['id', 'name', 'theme'], array_map(static fn($field) => $field->name, $type->fields));
    }

    public function test_typed_extras_still_make_an_unsealed_shape(): void
    {
        $type = (new PhpDocShapeParser())->parse('array{id: string, ...<string, int>}');

        self::assertInstanceOf(ShapeType::class, $type);
        self::assertTrue($type->unsealed);
    }

    public function test_a_sealed_shape_is_sealed(): void
    {
        $type = (new PhpDocShapeParser())->parse('array{id: string}');

        self::assertInstanceOf(ShapeType::class, $type);
        self::assertFalse($type->unsealed);
    }

    public function test_an_unsealed_tuple_keeps_its_elements(): void
    {
        $type = (new PhpDocShapeParser())->parse('array{int, string, ...}');

        self::assertInstanceOf(TupleType::class, $type);
        self::assertCount(2, $type->elements);
        self::assertTrue($type->unsealed);
    }

    /**
     * An enum's serialised form as ApiEnumInterface-style code writes it: the shared envelope,
     * plus the enum's own keys, and more keys allowed.
     */
    public function test_an_intersection_with_an_unsealed_shape(): void
    {
        $type = (new PhpDocShapeParser())->parse('(ApiEnumData & array{ok: bool, value: int, ...})');

        self::assertInstanceOf(IntersectionType::class, $type);
        self::assertSame('ApiEnumData', $type->base->name);
        self::assertTrue($type->extra->unsealed);
        self::assertSame(['ok', 'value'], array_map(static fn($field) => $field->name, $type->extra->fields));
    }

    public function test_a_constant_wildcard_is_left_for_the_owning_class_to_resolve(): void
    {
        $type = (new PhpDocShapeParser())->parse('self::RESULT_*');

        self::assertInstanceOf(ClassConstantType::class, $type);
        self::assertSame('self', $type->class);
        self::assertSame('RESULT_*', $type->pattern);
        self::assertFalse($type->valueOf);
    }

    public function test_value_of_a_constant_wildcard(): void
    {
        $type = (new PhpDocShapeParser())->parse('list<value-of<self::OPTION_SETS_*>>');

        self::assertInstanceOf(ListType::class, $type);
        self::assertInstanceOf(ClassConstantType::class, $type->inner);
        self::assertTrue($type->inner->valueOf);
    }

    public function test_a_constant_of_a_fully_qualified_class(): void
    {
        $type = (new PhpDocShapeParser())->parse('\\Acme\\Review\\Result::APPROVED');

        self::assertInstanceOf(ClassConstantType::class, $type);
        self::assertSame('\\Acme\\Review\\Result', $type->class);
        self::assertSame('APPROVED', $type->pattern);
    }

    private static function scalarKey(MapType $map): string
    {
        self::assertInstanceOf(ScalarType::class, $map->key);

        return $map->key->type;
    }
}
