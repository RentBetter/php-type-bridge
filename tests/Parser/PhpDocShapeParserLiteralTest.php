<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Parser;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Parser\LiteralType;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Parser\UnionType;
use RuntimeException;

final class PhpDocShapeParserLiteralTest extends TestCase
{
    public function test_it_parses_string_int_float_and_bool_literals(): void
    {
        $parser = new PhpDocShapeParser();

        self::assertLiteral('draft', $parser->parse("'draft'"));
        self::assertLiteral('draft', $parser->parse('"draft"'));
        self::assertLiteral(42, $parser->parse('42'));
        self::assertLiteral(-1, $parser->parse('-1'));
        self::assertLiteral(3.14, $parser->parse('3.14'));
        self::assertLiteral(true, $parser->parse('true'));
        self::assertLiteral(false, $parser->parse('false'));
    }

    public function test_it_parses_literal_unions_inside_shapes(): void
    {
        $shape = (new PhpDocShapeParser())->parse("array{status: 'draft'|'complete', count: 0|1}");

        self::assertInstanceOf(ShapeType::class, $shape);
        $fields = [];
        foreach ($shape->fields as $field) {
            $fields[$field->name] = $field->type;
        }

        self::assertInstanceOf(UnionType::class, $fields['status']);
        self::assertLiteral('draft', $fields['status']->types[0]);
        self::assertLiteral('complete', $fields['status']->types[1]);
        self::assertInstanceOf(UnionType::class, $fields['count']);
        self::assertLiteral(0, $fields['count']->types[0]);
        self::assertLiteral(1, $fields['count']->types[1]);
    }

    public function test_it_supports_postfix_optional_keys(): void
    {
        $shape = (new PhpDocShapeParser())->parse('array{id: string, legacy?: int}');

        self::assertInstanceOf(ShapeType::class, $shape);
        $optional = [];
        foreach ($shape->fields as $field) {
            $optional[$field->name] = $field->optional;
        }

        self::assertFalse($optional['id']);
        self::assertTrue($optional['legacy']);
    }

    public function test_it_rejects_unterminated_string_literal(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unterminated string literal');

        (new PhpDocShapeParser())->parse("array{status: 'draft}");
    }

    private static function assertLiteral(string|int|float|bool $expected, object $type): void
    {
        self::assertInstanceOf(LiteralType::class, $type);
        self::assertSame($expected, $type->value);
    }
}
