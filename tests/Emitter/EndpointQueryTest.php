<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
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
