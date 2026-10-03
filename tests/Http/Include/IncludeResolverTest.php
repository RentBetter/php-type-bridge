<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Http\Include;

use LogicException;
use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Http\Include\EnumCase;
use PTGS\TypeBridge\Http\Include\IncludeResolver;
use PTGS\TypeBridge\Http\Include\IncludeTree;
use PTGS\TypeBridge\Http\Include\InvalidIncludeException;
use PTGS\TypeBridge\Http\Include\Optional;
use PTGS\TypeBridge\Http\Include\Ref;
use PTGS\TypeBridge\Http\Include\RefRegistry;
use PTGS\TypeBridge\Tests\Http\Include\Fixtures\CheckStatus;
use PTGS\TypeBridge\Tests\Http\Include\Fixtures\Priority;
use PTGS\TypeBridge\Tests\Http\Include\Fixtures\RowsResponse;
use PTGS\TypeBridge\Tests\Http\Include\Fixtures\Thing;
use PTGS\TypeBridge\Tests\Http\Include\Fixtures\ThingNormalizer;
use PTGS\TypeBridge\Tests\Http\Include\Fixtures\ThingProxy;

final class IncludeResolverTest extends TestCase
{
    private ThingNormalizer $things;
    private IncludeResolver $resolver;

    protected function setUp(): void
    {
        $this->things = new ThingNormalizer();
        $this->resolver = new IncludeResolver(new RefRegistry([$this->things]));
    }

    public function testOptionalsResolveOnlyWhereTheirPathIsIncluded(): void
    {
        $response = new RowsResponse(rows: [
            ['id' => 'a', 'debug' => new Optional(static fn (): array => ['seen' => 1]), 'documentation' => new Optional(static fn (): string => 'How it works')],
            ['id' => 'b', 'debug' => new Optional(static fn (): array => ['seen' => 2]), 'documentation' => new Optional(static fn (): string => 'How it works')],
        ]);

        $body = $this->body($response, include: 'rows.debug');
        self::assertSame([['id' => 'a', 'debug' => ['seen' => 1]], ['id' => 'b', 'debug' => ['seen' => 2]]], $body['rows']);

        $body = $this->body($response, include: 'rows.*');
        self::assertSame(['id', 'debug', 'documentation'], array_keys(self::row($body)));
    }

    public function testAnOptionalNobodyAskedForIsNeverComputed(): void
    {
        $response = new RowsResponse(rows: [
            ['id' => 'a', 'debug' => new Optional(static fn () => throw new LogicException('computed without being asked for'))],
        ]);

        self::assertSame([['id' => 'a']], $this->body($response)['rows']);
    }

    public function testAnOptionalResponseFieldIsDroppedUnlessIncluded(): void
    {
        $response = new RowsResponse(rows: [], summary: new Optional(static fn (): array => ['total' => 0]));

        self::assertSame(['rows' => []], $this->body($response));
        self::assertSame(['rows' => [], 'summary' => ['total' => 0]], $this->body($response, include: 'summary'));
    }

    public function testARefIsItsIdUnlessItsPathIsExpanded(): void
    {
        $response = new RowsResponse(rows: [['id' => 'a', 'thing' => self::ref('thing-1')]]);

        self::assertSame([['id' => 'a', 'thing' => 'thing-1']], $this->body($response)['rows']);
        self::assertSame([], $this->things->fetched, 'Nothing is fetched for a ref left as an id');

        $expanded = self::row($this->body($response, expand: 'rows.thing'))['thing'];
        self::assertSame(['id' => 'thing-1', 'name' => 'Thing thing-1', 'next' => 'thing-2'], $expanded, 'The record, with its own refs as ids and its optionals left out');
    }

    public function testARefToAProxyExpandsThroughItsEntitysNormalizer(): void
    {
        $response = new RowsResponse(rows: [['id' => 'a', 'thing' => new Ref(ThingProxy::class, 'thing-2')]]);

        self::assertSame('Thing thing-2', self::row($this->body($response, expand: 'rows.thing(name)'))['thing']['name'] ?? null);
    }

    public function testEveryRefAtALevelIsFetchedInOneGoAndEachIdOnce(): void
    {
        $response = new RowsResponse(rows: [
            ['id' => 'a', 'thing' => self::ref('thing-1')],
            ['id' => 'b', 'thing' => self::ref('thing-2')],
            ['id' => 'c', 'thing' => self::ref('thing-1')],
        ]);

        $rows = $this->body($response, expand: 'rows.thing')['rows'];
        self::assertIsArray($rows);

        self::assertSame(['thing-1', 'thing-2', 'thing-1'], array_column(array_column($rows, 'thing'), 'id'));
        self::assertSame([['thing-1', 'thing-2']], $this->things->fetched);
    }

    public function testAPathGoesOnIntoTheRecordItExpandsToAndIncludesThere(): void
    {
        $response = new RowsResponse(rows: [['id' => 'a', 'thing' => self::ref('thing-1')]]);

        $thing = self::row($this->body($response, include: 'rows.thing.debug', expand: 'rows.thing.next'))['thing'];
        self::assertIsArray($thing);

        self::assertSame(['seen' => 'thing-1'], $thing['debug']);
        self::assertIsArray($thing['next']);
        self::assertSame('thing-2', $thing['next']['id']);
        self::assertSame('thing-1', $thing['next']['next'], 'A reference cycle is followed only as far as the path spells out');
        self::assertSame([['thing-1'], ['thing-2']], $this->things->fetched, 'One fetch per level');
    }

    public function testAListOfRefsExpandsItemByItem(): void
    {
        $response = new RowsResponse(rows: [['id' => 'a', 'things' => [self::ref('thing-1'), self::ref('thing-2')]]]);

        self::assertSame(['thing-1', 'thing-2'], self::row($this->body($response))['things']);

        $things = self::row($this->body($response, expand: 'rows.things'))['things'];
        self::assertIsArray($things);
        self::assertSame(['Thing thing-1', 'Thing thing-2'], array_column($things, 'name'));
    }

    public function testAFieldListNarrowsAnExpandedRecordToItsIdAndThoseFields(): void
    {
        $response = new RowsResponse(rows: [['id' => 'a', 'thing' => self::ref('thing-1'), 'things' => [self::ref('thing-1'), self::ref('thing-2')]]]);

        $row = self::row($this->body($response, expand: 'rows.thing(name),rows.things(name)'));
        self::assertSame(['id' => 'thing-1', 'name' => 'Thing thing-1'], $row['thing']);
        self::assertSame([['id' => 'thing-1', 'name' => 'Thing thing-1'], ['id' => 'thing-2', 'name' => 'Thing thing-2']], $row['things']);

        // A field the record lacks is skipped: only the shape could say it is not one.
        self::assertSame(['id' => 'thing-1'], self::row($this->body($response, expand: 'rows.thing(nope)'))['thing']);
    }

    public function testAFieldListKeepsWhatADeeperPathGoesOnInto(): void
    {
        $response = new RowsResponse(rows: [['id' => 'a', 'thing' => self::ref('thing-1')]]);

        $thing = self::row($this->body($response, include: 'rows.thing.debug', expand: 'rows.thing(name).next(name)'))['thing'];
        self::assertIsArray($thing);

        self::assertSame(['id', 'name', 'next', 'debug'], array_keys($thing));
        self::assertSame(['id' => 'thing-2', 'name' => 'Thing thing-2'], $thing['next']);
    }

    public function testAFieldListNarrowsTheRowsThemselvesAndNests(): void
    {
        $response = new RowsResponse(rows: [['id' => 'a', 'name' => 'A', 'meta' => ['x' => 1, 'y' => 2], 'thing' => self::ref('thing-1')]]);

        self::assertSame([['id' => 'a', 'name' => 'A']], $this->body($response, expand: 'rows(name)')['rows']);
        self::assertSame([['id' => 'a', 'meta' => ['x' => 1]]], $this->body($response, expand: 'rows(meta(x))')['rows']);

        // `rows(thing(name))` is `rows(thing)` and `rows.thing(name)`: the ref expands, narrowed.
        self::assertSame(
            [['id' => 'a', 'thing' => ['id' => 'thing-1', 'name' => 'Thing thing-1']]],
            $this->body($response, expand: 'rows(thing(name))')['rows'],
        );
    }

    public function testAnApiEnumIsItsCaseOrItsIdUnlessExpandedAndThenWholeOrNarrowed(): void
    {
        $response = new RowsResponse(rows: [['id' => 'a', 'status' => new EnumCase(CheckStatus::Warning)]]);

        self::assertSame(CheckStatus::Warning, self::row($this->body($response))['status']);
        self::assertSame('WARNING', self::row($this->body($response, enumIds: true))['status']);

        // Expanded, it goes out whole even to a request that asked for ids, or cut to a list.
        self::assertSame(['id' => 'WARNING', 'name' => 'Warning', 'theme' => 'amber'], self::row($this->body($response, expand: 'rows.status', enumIds: true))['status']);
        self::assertSame(['id' => 'WARNING', 'name' => 'Warning'], self::row($this->body($response, expand: 'rows(id,status(name))', enumIds: true))['status']);
    }

    public function testAPlainBackedEnumIsItsValueWhicheverTheFormat(): void
    {
        $response = new RowsResponse(rows: [['id' => 'a', 'priority' => new EnumCase(Priority::High)]]);

        self::assertSame(Priority::High, self::row($this->body($response))['priority'], 'json_encode writes it as its value');
        self::assertSame(2, self::row($this->body($response, enumIds: true))['priority']);
        self::assertSame('{"rows":[{"id":"a","priority":2}],"summary":null}', json_encode($this->body($response)));
    }

    public function testExpandingWhatHasNothingToExpandIsRefused(): void
    {
        $response = new RowsResponse(rows: [[
            'id' => 'a',
            'status' => new EnumCase(CheckStatus::Ok),
            'priority' => new EnumCase(Priority::Low),
            'meta' => ['x' => 1],
        ]]);

        $refusals = [
            'rows.id' => 'is a single value',
            'rows.meta' => 'is not a reference',
            'rows.status.name' => 'is an enum',
            'rows.priority' => 'is a single value',
            'rows.id(x)' => 'is a single value',
        ];
        foreach ($refusals as $expand => $why) {
            try {
                $this->body($response, expand: $expand);
                self::fail("Expanding {$expand} was not refused");
            } catch (InvalidIncludeException $e) {
                self::assertSame('expand', $e->parameter, $expand);
                self::assertStringContainsString($why, $e->getMessage(), $expand);
            }
        }
    }

    public function testAPathThisResponseHasNothingForIsRefused(): void
    {
        $response = new RowsResponse(rows: []);

        foreach (['include' => 'silences', 'expand' => 'definitions'] as $param => $path) {
            try {
                $this->body($response, ...[$param => $path]);
                self::fail("{$param}={$path} was not refused");
            } catch (InvalidIncludeException $e) {
                self::assertSame($param, $e->parameter);
                self::assertStringContainsString('whose fields are rows, summary', $e->getMessage());
            }
        }
    }

    public function testARefNothingExpandsIsAServerBugNotARequestError(): void
    {
        $response = new RowsResponse(rows: [['id' => 'a', 'thing' => new Ref(self::class, 'x')]]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('needs to implement');

        $this->body($response, expand: 'rows.thing');
    }

    public function testARefWhoseRecordIsNotFoundIsAServerBug(): void
    {
        $response = new RowsResponse(rows: [['id' => 'a', 'thing' => self::ref('thing-404')]]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('fetching it found nothing');

        $this->body($response, expand: 'rows.thing');
    }

    /**
     * @return array<array-key, mixed>
     */
    private function body(RowsResponse $response, string $include = '', string $expand = '', bool $enumIds = false): array
    {
        return $this->resolver->body($response, IncludeTree::parse($include), IncludeTree::parseExpand($expand), $enumIds);
    }

    /**
     * The first row of a body.
     *
     * @param array<array-key, mixed> $body
     *
     * @return array<array-key, mixed>
     */
    private static function row(array $body): array
    {
        self::assertIsArray($body['rows']);
        self::assertIsArray($body['rows'][0]);

        return $body['rows'][0];
    }

    private static function ref(string $id): Ref
    {
        return new Ref(Thing::class, $id);
    }
}
