<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Parser\LiteralType;
use PTGS\TypeBridge\Parser\ParsedType;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Parser\UnionType;
use PTGS\TypeBridge\Support\PhpDocTypeHelper;
use RuntimeException;

/**
 * Class constants resolve to the literal values PHPStan reads them as, against the class that
 * owns the shape, and `value-of<>` takes of them together what PHPStan's own does. A form PHPStan
 * cannot read stops generation, rather than emit a type for a shape that goes unchecked, and so
 * does null, which only the shape's own nullability may bring. The plain wildcard and an array
 * constant's elements are covered end to end by the grammar fixtures.
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
        self::assertEquals(
            new UnionType([new LiteralType(1), new LiteralType(2)]),
            $this->resolve('value-of<self::*>', ConstantFixturePriority::class),
        );
    }

    public function test_a_value_several_constants_hold_is_taken_once(): void
    {
        self::assertEquals(
            new UnionType([new LiteralType('x'), new LiteralType('y'), new LiteralType('z')]),
            $this->resolve('value-of<self::SHARED_*>'),
        );
    }

    public function test_an_empty_array_beside_one_with_values_adds_nothing(): void
    {
        self::assertEquals(new LiteralType('z'), $this->resolve('value-of<self::SOME_*>'));
    }

    public function test_the_class_keywords_resolve_as_phpstan_resolves_them(): void
    {
        self::assertEquals(new LiteralType('simple'), $this->resolve('Self::MODE_SIMPLE'));
        self::assertEquals(new LiteralType('base'), $this->resolve('parent::BASE', ConstantFixtureChild::class));
        self::assertEquals(new LiteralType('simple'), $this->resolve('static::MODE_SIMPLE'));
        self::assertEquals(
            new UnionType([new LiteralType('base'), new LiteralType('late')]),
            $this->resolve('static::*', ConstantFixtureBase::class),
        );
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
        yield 'value-of of a null constant, where the bare form would fail too' => [
            'value-of<self::NOTHING>',
            $fixture,
            "`value-of<self::NOTHING>` matches {$fixture}::NOTHING, which is neither an array nor an enum case: value-of<> takes an array's elements or a case's backing value, and PHPStan reads it of anything else as an invalid type.",
        ];
        yield 'value-of of an enum case beside an array' => [
            'value-of<self::*>',
            ConstantFixtureMixed::class,
            '`value-of<self::*>` matches the enum case ' . ConstantFixtureMixed::class . '::ON and ' . ConstantFixtureMixed::class . "::EXTRA, which is not one: value-of<> takes cases' backing values only when every constant it matches is a case, and PHPStan reads a mix as an invalid type.",
        ];
        yield 'one constant through static:: on a class that is not final' => [
            'static::LATE',
            ConstantFixtureBase::class,
            '`static::LATE` names one constant through static:: on ' . ConstantFixtureBase::class . ', which is not final, so PHPStan reads it as the type the constant declares rather than its value. Write `self::LATE`.',
        ];
        yield 'parent:: on a class with no parent' => [
            'parent::MODE_SIMPLE',
            $fixture,
            "`parent::MODE_SIMPLE` is written on {$fixture}, which has no parent class.",
        ];
        yield 'a bare enum case, pointing to value-of' => [
            'self::ACTIVE',
            ConstantFixtureStatus::class,
            '`self::ACTIVE` matches ' . ConstantFixtureStatus::class . '::ACTIVE, whose value is not a string, number or bool, so it has no literal type. Write `value-of<self::ACTIVE>` for the backing values.',
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
            "`value-of<self::NESTED>` matches {$fixture}::NESTED, whose element 1 is not a string, number or bool, so it has no literal type.",
        ];
        yield 'a null constant' => [
            'self::NOTHING',
            $fixture,
            "`self::NOTHING` matches {$fixture}::NOTHING, whose value is null. Write nullability in the shape, as `?T` or `T|null`, so preserveNull governs it.",
        ];
        yield 'value-of of an array with a null element' => [
            'value-of<self::MAYBE>',
            $fixture,
            "`value-of<self::MAYBE>` matches {$fixture}::MAYBE, whose element 1 is null. Write nullability in the shape, as `?T` or `T|null`, so preserveNull governs it.",
        ];
        yield 'an array constant on its own' => [
            'self::NESTED',
            $fixture,
            "`self::NESTED` matches {$fixture}::NESTED, whose value is not a string, number or bool, so it has no literal type.",
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
    public const array SHARED_A = ['x', 'y'];
    public const array SHARED_B = ['y', 'z'];
    public const array SOME_NONE = [];
    public const array SOME_ONE = ['z'];
}

class ConstantFixtureBase
{
    public const string BASE = 'base';
    public const string LATE = 'late';
}

final class ConstantFixtureChild extends ConstantFixtureBase
{
}

enum ConstantFixtureStatus: string
{
    case ACTIVE = 'active';
    case PAUSED = 'paused';

    public const self DEFAULT = self::ACTIVE;
}

enum ConstantFixturePriority: int
{
    case LOW = 1;
    case HIGH = 2;
}

enum ConstantFixtureSwitch
{
    case ON;
}

enum ConstantFixtureMixed: string
{
    case ON = 'on';

    public const array EXTRA = ['x'];
}
