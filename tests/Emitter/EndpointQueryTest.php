<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;

/**
 * An Endpoint's query is `query?` when a caller may leave it out and `query` when its contract
 * requires a key — so a typed helper refuses, at compile time, the call the API would refuse.
 */
final class EndpointQueryTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/RequiredQueryFixtures';

    public function test_a_query_with_a_required_key_cannot_be_left_out(): void
    {
        $reports = $this->emit()['Reports'];

        self::assertStringContainsString('export const ShowReport: Endpoint<ShowReportEndpointMap, { query: ShowReportQuery }> = {', $reports);
        self::assertStringContainsString("export interface ReportFilterData {\n  product: string;\n  week?: string;\n}", $reports);
    }

    public function test_a_query_whose_every_key_is_optional_can(): void
    {
        self::assertStringContainsString('export const ListReports: Endpoint<ListReportsEndpointMap, { query?: ListReportsQuery }> = {', $this->emit()['Reports']);
    }

    public function test_a_required_key_inherited_through_an_intersection_makes_the_query_required(): void
    {
        $reports = $this->emit()['Reports'];

        self::assertStringContainsString('export const ShowWeeklyReport: Endpoint<ShowWeeklyReportEndpointMap, { query: ShowWeeklyReportQuery }> = {', $reports);
    }

    public function test_the_keys_an_intersection_inherits_are_read_where_its_base_is_declared(): void
    {
        $responses = (new ResponseClassCollector())->collectIndex(self::SRC);
        $queries = [];
        foreach ((new EndpointContractCollector())->collect(self::SRC, $responses)['Reports'] as $contract) {
            $queries[$contract->name] = $contract->request?->query;
        }
        $keys = static fn(?ShapeType $shape): array => array_map(static fn(ShapeField $key): string => $key->name, $shape->fields ?? []);

        // Imported from another class.
        self::assertSame(['week'], $keys($queries['ShowWeeklyReport']?->contract));
        self::assertSame(['product'], $keys($queries['ShowWeeklyReport']?->inheritedContract));
        // Through a base that is an intersection itself.
        self::assertSame(['product', 'team'], $keys($queries['ShowQuarterlyReport']?->inheritedContract));
        // Declared in the same file.
        self::assertSame(['product'], $keys($queries['ShowMonthlyReport']?->inheritedContract));
        // Not an intersection.
        self::assertNull($queries['ShowReport']?->inheritedContract);

        $reports = $this->emit()['Reports'];
        self::assertStringContainsString('export const ShowQuarterlyReport: Endpoint<ShowQuarterlyReportEndpointMap, { query: ShowQuarterlyReportQuery }> = {', $reports);
        self::assertStringContainsString('export const ShowMonthlyReport: Endpoint<ShowMonthlyReportEndpointMap, { query: ShowMonthlyReportQuery }> = {', $reports);
    }

    public function test_a_key_valued_question_mark_t_is_optional_and_so_is_the_query(): void
    {
        $reports = $this->emit()['Reports'];

        self::assertStringContainsString("export interface NullableProductFilterData {\n  product?: string;\n}", $reports);
        self::assertStringContainsString('export const ListProductReports: Endpoint<ListProductReportsEndpointMap, { query?: ListProductReportsQuery }> = {', $reports);
    }

    public function test_a_key_that_may_be_null_is_one_a_query_may_leave_out(): void
    {
        // A query string carries no null: `product: string|null` is sent by leaving it out — the
        // query as a whole, and the key in a query that sends others.
        $reports = $this->emit()['Reports'];

        self::assertStringContainsString('export const ListAnyProductReports: Endpoint<ListAnyProductReportsEndpointMap, { query?: ListAnyProductReportsQuery }> = {', $reports);
        self::assertStringContainsString("export interface AnyProductFilterData {\n  product?: string | null;\n}", $reports);
    }

    public function test_an_inherited_key_that_may_be_null_is_one_a_query_may_leave_out(): void
    {
        // The shape it extends is its own interface, whatever else uses it: the query leaves the
        // key out without changing it.
        $reports = $this->emit()['Reports'];

        self::assertStringContainsString('export const ListAnyRegionReports: Endpoint<ListAnyRegionReportsEndpointMap, { query?: ListAnyRegionReportsQuery }> = {', $reports);
        self::assertStringContainsString("export interface RegionScope {\n  region: string | null;\n}", $reports);
        self::assertStringContainsString("export interface AnyRegionFilterData extends Omit<RegionScope, 'region'> {\n  region?: RegionScope['region'];\n  page?: number;\n}", $reports);
    }

    public function test_keys_inherited_through_a_base_that_cant_be_read_are_unknown_not_partial(): void
    {
        // ScopeData's own keys are readable, the root it extends is outside the source tree: what
        // it inherits is not known, and a part of it is not passed off as the whole.
        $src = __DIR__ . '/../Fixture/PartialInheritedQueryFixtures';
        $contract = (new EndpointContractCollector())->collect($src, (new ResponseClassCollector())->collectIndex($src))['Reports'][0];

        self::assertSame('ShowReport', $contract->name);
        self::assertNull($contract->request?->query?->inheritedContract);
    }

    /**
     * @return array<string, string>
     */
    private function emit(): array
    {
        $responseCollector = new ResponseClassCollector();
        $enumResolver = new EnumResolver();
        $enumResolver->scanDirectory(self::SRC);

        return (new TypeScriptEmitter(
            enumResolver: $enumResolver,
            domainMapper: new DomainMapper('/tmp/type-bridge-output'),
            // A null that means something, so the contract says `T|null`: in a query, "any product".
            preserveNull: ['AnyProductFilterData.product', 'RegionScope.region'],
        ))->emit(
            (new PhpDocTypeCollector())->collect(self::SRC),
            $responseCollector->collect(self::SRC),
            (new EndpointContractCollector())->collect(self::SRC, $responseCollector->collectIndex(self::SRC)),
        );
    }
}
