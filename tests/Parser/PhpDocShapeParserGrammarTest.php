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
        $type = (new PhpDocShapeParser())->parse('list<value-of<self::PORTAL_INPUT_TYPE_*>>');

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
}
