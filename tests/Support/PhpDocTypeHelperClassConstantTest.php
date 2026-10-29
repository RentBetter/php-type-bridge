<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Support;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Parser\LiteralType;
use PTGS\TypeBridge\Parser\ListType;
use PTGS\TypeBridge\Parser\ParsedType;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Parser\UnionType;
use PTGS\TypeBridge\Support\PhpDocTypeHelper;
use PTGS\TypeBridge\Tests\Fixture\GrammarFixtures\Catalogue\Review\Result;
use RuntimeException;

/**
 * Class constants resolve to the literal values PHPStan reads them as, against the class that
 * owns the shape — and a form PHPStan cannot read stops generation rather than emit a type for it.
 */
final class PhpDocTypeHelperClassConstantTest extends TestCase
{
    public function test_a_constant_wildcard_is_the_values_of_the_constants_it_matches(): void
    {
        self::assertEquals(
            new ListType(new UnionType([new LiteralType('simple'), new LiteralType('full')])),
            $this->resolve('list<self::MODE_*>'),
        );
    }

    public function test_value_of_an_array_constant_is_its_elements(): void
    {
        self::assertEquals(
            new UnionType([new LiteralType('simple'), new LiteralType('full')]),
            $this->resolve('value-of<self::MODES>'),
        );
    }

    public function test_value_of_a_constant_that_is_not_an_array_is_an_error(): void
    {
        // PHPStan reads `value-of<>` of a string as an invalid type, so a shape written this way
        // goes unchecked: the generator refuses it rather than emit the constants' values.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            '`value-of<self::MODE_*>` matches ' . Result::class . '::MODE_SIMPLE, which is not an array'
            . ': value-of<> takes the elements of an array constant, and PHPStan reads it of any other as an invalid type.'
            . ' Write `self::MODE_*` for the constants\' own values.',
        );

        $this->resolve('list<value-of<self::MODE_*>>');
    }

    private function resolve(string $type): ParsedType
    {
        return (new PhpDocTypeHelper())->resolveImportedNames((new PhpDocShapeParser())->parse($type), [], Result::class);
    }
}
