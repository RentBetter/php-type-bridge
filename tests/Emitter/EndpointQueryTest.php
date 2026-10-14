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
        ))->emit(
            (new PhpDocTypeCollector())->collect(self::SRC),
            $responseCollector->collect(self::SRC),
            (new EndpointContractCollector())->collect(self::SRC, $responseCollector->collectIndex(self::SRC)),
        );
    }
}
