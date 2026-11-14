<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Mcp;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Mcp\PortableRegex;

/**
 * What a schema `pattern` may carry, read directly: the manifest builder's tests read it through
 * the tools they build.
 */
final class PortableRegexTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool|null, bool}>
     */
    public static function fragments(): iterable
    {
        yield 'a class, bounded' => ['[A-Z]{2,5}', false, true];
        yield 'a group and an escape' => ['(?:SO|BPP)-\d+', false, true];
        yield 'a dot' => ['a.b', false, false];
        yield '\s, whose sets differ' => ['a\sb', false, false];
        // PHP reads `\d` and `\w` as Unicode under `u`, ECMA as ASCII: published as their ASCII
        // sets, which take fewer, never what the server refuses — but not inside a negated class,
        // where fewer refused is more taken. Their negations and `\b` read the two ways.
        yield '\d under u, published as [0-9]' => ['\d+', true, true];
        yield '\d not knowing the mode' => ['\d+', null, true];
        yield '\w in a class under u' => ['[\w-]+', true, true];
        yield '\d in a negated class under u' => ['[^\d]+', true, false];
        yield '\D under u' => ['\D', true, false];
        yield '\b not knowing the mode' => ['\ba', null, false];
        yield 'a negated class by byte' => ['[^a]+', false, false];
        yield 'a negated class by character' => ['[^a]+', true, true];
        yield 'a range out of order' => ['[z-a]', false, false];
        yield 'a possessive quantifier' => ['a++', false, false];
    }

    #[DataProvider('fragments')]
    public function testItReadsAFragmentAsBothDialectsDo(string $fragment, ?bool $unicode, bool $same): void
    {
        self::assertSame($same, PortableRegex::readsTheSame($fragment, alternationOutsideGroups: false, unicode: $unicode));
    }

    public function testAnAlternationOutsideAGroupIsTheCallersToWrap(): void
    {
        self::assertTrue(PortableRegex::readsTheSame('a|b', alternationOutsideGroups: true, unicode: false));
        self::assertFalse(PortableRegex::readsTheSame('a|b', alternationOutsideGroups: false, unicode: false));
    }

    /**
     * @return iterable<string, array{string, array{0: string, 1: string}|null}>
     */
    public static function rules(): iterable
    {
        yield 'anchored, D' => ['/^[a-z]+$/D', ['[a-z]+', 'D']];
        yield 'another delimiter, escaped inside' => ['#^a\#b$#', ['a#b', '']];
        yield 'bracket delimiters' => ['{^[0-9]{3}$}', ['[0-9]{3}', '']];
        yield 'unanchored' => ['/[a-z]+/', null];
        yield 'alternation outside a group' => ['/^a|b$/', null];
        yield 'a flag but D and u' => ['/^[a-z]+$/i', null];
        yield 'an escaped closing anchor' => ['/^a\$/', null];
    }

    /**
     * @param array{0: string, 1: string}|null $rule
     */
    #[DataProvider('rules')]
    public function testItReadsAWholeValueRule(string $pattern, ?array $rule): void
    {
        self::assertSame($rule, PortableRegex::wholeValueRule($pattern));
    }

    public function testAUnicodeDigitOrWordIsPublishedAsItsAsciiSet(): void
    {
        // Under `u`, or not knowing the mode, `\d` and `\w` are written out as the ASCII sets ECMA
        // reads them as, in a class too: what Python's `re`, reading them as Unicode, reads alike.
        self::assertSame('^(?:[A-Z]+-[0-9]+)$', PortableRegex::routePattern('[A-Z]+-\d+', null));
        self::assertSame('^(?:[A-Za-z0-9_-]+)$', PortableRegex::routePattern('[\w-]+', true));
        self::assertSame('^(?:\d+)$', PortableRegex::routePattern('\d+', false), 'by byte it is [0-9] already');
        self::assertSame(['[0-9]{4}', 'u'], PortableRegex::wholeValueRule('/^\d{4}$/u'));
        self::assertSame(['\\d', ''], PortableRegex::wholeValueRule('/^\\d$/'), 'an escaped backslash and a d');
    }

    public function testARouteRequirementIsAnchoredWhole(): void
    {
        self::assertSame('^(?:[0-9]+)$', PortableRegex::routePattern('[0-9]+', false));
        self::assertSame('^(?:a|b)$', PortableRegex::routePattern('a|b', false));
        self::assertNull(PortableRegex::routePattern('%app.locales%', false), 'A container placeholder the router resolves');
        self::assertNull(PortableRegex::routePattern('', false));
    }
}
