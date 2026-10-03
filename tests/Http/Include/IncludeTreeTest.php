<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Http\Include;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Http\Include\IncludeTree;
use PTGS\TypeBridge\Http\Include\InvalidIncludeException;

final class IncludeTreeTest extends TestCase
{
    public function testAPathImpliesEveryPrefix(): void
    {
        $tree = IncludeTree::parse('checks.lastRun.results.debug');

        $checks = $tree->child('checks');
        self::assertNotNull($checks);
        $lastRun = $checks->child('lastRun');
        self::assertNotNull($lastRun);
        $results = $lastRun->child('results');
        self::assertNotNull($results);
        self::assertTrue($results->has('debug'));
        self::assertFalse($results->has('readings'));
    }

    public function testPathsMergeAndWhitespaceIsIgnored(): void
    {
        $tree = IncludeTree::parse(' definitions , checks.debug,checks.documentation ,, ');

        self::assertSame(['definitions', 'checks'], $tree->keys());
        self::assertTrue($tree->child('checks')?->has('debug'));
        self::assertTrue($tree->child('checks')?->has('documentation'));
        self::assertFalse($tree->has('silences'));
    }

    public function testAWildcardOpensOneLevelOnly(): void
    {
        $checks = IncludeTree::parse('checks.*')->child('checks');
        self::assertNotNull($checks);

        self::assertTrue($checks->has('debug'));
        self::assertTrue($checks->has('documentation'));
        self::assertFalse($checks->child('debug')?->has('trend'), 'A wildcard opens the key, not the optional keys beneath it');
        self::assertSame([], $checks->keys(), 'A wildcard names nothing explicitly');
    }

    public function testAnEmptyIncludeIsAnEmptyTree(): void
    {
        $tree = IncludeTree::parse('');

        self::assertSame([], $tree->keys());
        self::assertNull($tree->child('checks'));
    }

    public function testAnExpandParsesAsAnIncludeDoes(): void
    {
        $tree = IncludeTree::parseExpand('checks.definition.latestResult, checks.silence');

        self::assertSame(['checks'], $tree->keys());
        self::assertSame(['definition', 'silence'], $tree->child('checks')?->keys());
        self::assertTrue($tree->child('checks')?->child('definition')?->has('latestResult'));
    }

    public function testAnExpandHasNoWildcard(): void
    {
        try {
            IncludeTree::parseExpand('checks.*');
            self::fail('A wildcard expand was not refused');
        } catch (InvalidIncludeException $e) {
            self::assertSame('expand', $e->parameter);
            self::assertStringContainsString('each one is a query', $e->getMessage());
        }
    }

    public function testAnExpandSegmentCanCarryAFieldList(): void
    {
        $checks = IncludeTree::parseExpand('checks.definition(name, cadence).latestResult, checks.silence(by)')->child('checks');
        self::assertNotNull($checks);

        self::assertNull($checks->fields(), 'A segment without a list is the whole record');
        self::assertSame(['name', 'cadence'], $checks->child('definition')?->fields());
        self::assertTrue($checks->child('definition')?->has('latestResult'), 'A path goes on past a field list');
        self::assertSame(['by'], $checks->child('silence')?->fields());
    }

    public function testAFieldCanCarryItsOwnListAsTheDottedPathWould(): void
    {
        $account = IncludeTree::parseExpand('property.account(status(name), owner)')->child('property')?->child('account');
        self::assertNotNull($account);

        self::assertSame(['status', 'owner'], $account->fields());
        self::assertSame(['name'], $account->child('status')?->fields());
        self::assertNull($account->child('owner'), 'A plain field opens nothing beneath it');
    }

    public function testFieldListsMergeAndAPathEndingWithoutOneAsksForTheWholeRecord(): void
    {
        $definition = static fn (string $expand): ?array => IncludeTree::parseExpand($expand)->child('checks')?->child('definition')?->fields();

        self::assertSame(['name', 'cadence'], $definition('checks.definition(name),checks.definition(cadence,name)'));
        self::assertNull($definition('checks.definition(name),checks.definition'));
        self::assertSame(['name'], $definition('checks.definition(name),checks.definition.latestResult'), 'Passing through does not widen it');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidExpands(): iterable
    {
        yield 'unclosed' => ['checks.definition(name'];
        yield 'unopened' => ['checks.definition(name))'];
        yield 'empty list' => ['checks.definition()'];
        yield 'trailing text' => ['checks.definition(name)x'];
        yield 'no key' => ['checks.(name)'];
        yield 'not a field name' => ['checks.definition(na-me)'];
        yield 'a dotted field' => ['checks(definition.name)'];
        yield 'an empty nested list' => ['checks(status())'];
        yield 'a wildcard' => ['checks.*'];
    }

    #[DataProvider('invalidExpands')]
    public function testMalformedExpandsAreRefused(string $expand): void
    {
        try {
            IncludeTree::parseExpand($expand);
            self::fail("{$expand} was not refused");
        } catch (InvalidIncludeException $e) {
            self::assertSame('expand', $e->parameter);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIncludes(): iterable
    {
        yield 'wildcard at the root' => ['*'];
        yield 'wildcard mid-path' => ['checks.*.debug'];
        yield 'empty segment' => ['checks..debug'];
        yield 'trailing dot' => ['checks.'];
        yield 'a field list' => ['checks.definition(name)'];
    }

    #[DataProvider('invalidIncludes')]
    public function testMalformedIncludesAreRefused(string $include): void
    {
        try {
            IncludeTree::parse($include);
            self::fail("{$include} was not refused");
        } catch (InvalidIncludeException $e) {
            self::assertSame('include', $e->parameter);
        }
    }
}
