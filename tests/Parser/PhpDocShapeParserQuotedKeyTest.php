<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Parser;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Parser\LiteralType;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Parser\TupleType;
use PTGS\TypeBridge\Support\PhpDocTypeHelper;
use RuntimeException;

/**
 * A key PHPDoc can only spell quoted — `'$type'`, `"content-type"` — is a key like any other.
 * The quotes are syntax, so the field's name is stored without them.
 */
final class PhpDocShapeParserQuotedKeyTest extends TestCase
{
    public function test_single_and_double_quoted_keys_are_stored_unquoted(): void
    {
        $shape = (new PhpDocShapeParser())->parse(<<<'DOC'
            array{'$type': string, "content-type": string, 'plain': int, id: string}
            DOC);

        self::assertInstanceOf(ShapeType::class, $shape);
        self::assertSame(['$type', 'content-type', 'plain', 'id'], self::names($shape));
    }

    public function test_a_quoted_key_can_be_optional(): void
    {
        $shape = (new PhpDocShapeParser())->parse("array{'\$type'?: string, \"x-retry\"?: int}");

        self::assertInstanceOf(ShapeType::class, $shape);
        self::assertTrue($shape->fields[0]->optional);
        self::assertTrue($shape->fields[1]->optional);
    }

    public function test_an_escaped_quote_inside_a_key_is_resolved(): void
    {
        $shape = (new PhpDocShapeParser())->parse(<<<'DOC'
            array{'it\'s': string, "say \"hi\"": string}
            DOC);

        self::assertInstanceOf(ShapeType::class, $shape);
        self::assertSame(["it's", 'say "hi"'], self::names($shape));
    }

    public function test_quoted_literals_without_a_colon_are_still_a_tuple(): void
    {
        $tuple = (new PhpDocShapeParser())->parse("array{'draft', 'complete'}");

        self::assertInstanceOf(TupleType::class, $tuple);
        self::assertInstanceOf(LiteralType::class, $tuple->elements[0]);
        self::assertSame('draft', $tuple->elements[0]->value);
    }

    public function test_a_quoted_key_survives_the_phpdoc_reader(): void
    {
        // The definition reaches the parser as phpdoc-parser prints it, not as it was typed.
        $definitions = (new PhpDocTypeHelper())->extractPhpStanTypes(<<<'PHP'
            <?php
            /**
             * @phpstan-type _self = array{
             *     '$type': string,
             *     "content-type"?: string,
             *     id: string,
             * }
             */
            final class Example {}
            PHP);

        $shape = (new PhpDocShapeParser())->parse($definitions['_self']);

        self::assertInstanceOf(ShapeType::class, $shape);
        self::assertSame(['$type', 'content-type', 'id'], self::names($shape));
        self::assertTrue($shape->fields[1]->optional);
    }

    public function test_an_unterminated_quoted_key_is_reported_as_such(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unterminated string literal');

        (new PhpDocShapeParser())->parse("array{'\$type: string}");
    }

    /**
     * @return list<string>
     */
    private static function names(ShapeType $shape): array
    {
        return array_map(static fn($field): string => $field->name, $shape->fields);
    }
}
