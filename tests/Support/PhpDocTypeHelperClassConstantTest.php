<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Parser\LiteralType;
use PTGS\TypeBridge\Parser\ParsedType;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Parser\ScalarType;
use PTGS\TypeBridge\Parser\UnionType;
use PTGS\TypeBridge\Support\PhpDocTypeHelper;
use RuntimeException;

/**
 * Class constants resolve to the literal values PHPStan reads them as, against the class that
 * owns the shape, and `value-of<>` takes of each what PHPStan's own does. A form PHPStan cannot
 * read stops generation, rather than emit a type for a shape that goes unchecked. The plain
 * wildcard and an array constant's elements are covered end to end by the grammar fixtures.
 */
final class PhpDocTypeHelperClassConstantTest extends TestCase
{
    public function test_value_of_an_enum_case_is_its_backing_value(): void
    {
        self::assertEquals(new LiteralType('active'), $this->resolve('value-of<self::ACTIVE>', ConstantFixtureStatus::class));
        self::assertEquals(
            new UnionType([new LiteralType('active'), new LiteralType('paused')]),
            $this->resolve('value-of<self::*>', ConstantFixtureStatus::class),
        );
    }

    public function test_a_null_constant_or_element_is_null(): void
    {
        self::assertEquals(new ScalarType('null'), $this->resolve('self::NOTHING'));
        self::assertEquals(new UnionType([new LiteralType('yes'), new ScalarType('null')]), $this->resolve('value-of<self::MAYBE>'));
    }

    /**
     * @return iterable<string, array{string, class-string, string}>
     */
    public static function refusals(): iterable
    {
        $fixture = ConstantFixture::class;

        yield 'value-of of strings, pointing to the bare form' => [
            'list<value-of<self::MODE_*>>',
            $fixture,
            "`value-of<self::MODE_*>` matches {$fixture}::MODE_SIMPLE, which is neither an array nor an enum case: value-of<> takes an array's elements or a case's backing value, and PHPStan reads it of anything else as an invalid type. Write `self::MODE_*` for the constants' own values.",
        ];
        yield 'value-of of a string beside an array, where the bare form would fail too' => [
            'value-of<self::MIXED_*>',
            $fixture,
            "`value-of<self::MIXED_*>` matches {$fixture}::MIXED_ONE, which is neither an array nor an enum case: value-of<> takes an array's elements or a case's backing value, and PHPStan reads it of anything else as an invalid type.",
        ];
        yield 'value-of of an empty array' => [
            'value-of<self::NONE>',
            $fixture,
            "`value-of<self::NONE>` takes no value from {$fixture}::NONE, so PHPStan reads it as never.",
        ];
        yield 'value-of of a pure enum case' => [
            'value-of<self::ON>',
            ConstantFixtureSwitch::class,
            '`value-of<self::ON>` takes no value from ' . ConstantFixtureSwitch::class . '::ON, so PHPStan reads it as never.',
        ];
        yield 'value-of of an array with an element that has no literal type' => [
            'value-of<self::NESTED>',
            $fixture,
            "`value-of<self::NESTED>` matches {$fixture}::NESTED, whose element 1 is not a string, number, bool or null, so it has no literal type.",
        ];
        yield 'an array constant on its own' => [
            'self::NESTED',
            $fixture,
            "`self::NESTED` matches {$fixture}::NESTED, whose value is not a string, number, bool or null, so it has no literal type.",
        ];
        yield 'a pattern that matches nothing' => [
            'value-of<self::MISSING_*>',
            $fixture,
            "`value-of<self::MISSING_*>` matches no constant of {$fixture}.",
        ];
    }

    /**
     * @param class-string $owner
     */
    #[DataProvider('refusals')]
    public function test_a_form_phpstan_cannot_read_is_refused(string $type, string $owner, string $message): void
    {
        try {
            $this->resolve($type, $owner);
        } catch (RuntimeException $e) {
            self::assertSame($message, $e->getMessage());

            return;
        }

        self::fail("Expected `{$type}` to be refused.");
    }

    /**
     * @param class-string $owner
     */
    private function resolve(string $type, string $owner = ConstantFixture::class): ParsedType
    {
        return (new PhpDocTypeHelper())->resolveImportedNames((new PhpDocShapeParser())->parse($type), [], $owner);
    }
}

final class ConstantFixture
{
    public const string MODE_SIMPLE = 'simple';
    public const string MODE_FULL = 'full';
    public const string MIXED_ONE = 'one';
    public const array MIXED_ALL = ['one', 'two'];
    public const array NONE = [];
    public const null NOTHING = null;
    public const array MAYBE = ['yes', null];
    public const array NESTED = ['a', ['b']];
}

enum ConstantFixtureStatus: string
{
    case ACTIVE = 'active';
    case PAUSED = 'paused';
}

enum ConstantFixtureSwitch
{
    case ON;
}
