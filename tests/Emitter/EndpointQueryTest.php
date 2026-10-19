<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Emitter;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\PhpDocTypeCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Mcp\McpManifestBuilder;
use PTGS\TypeBridge\Model\CollectedEndpointContract;
use PTGS\TypeBridge\Model\CollectedMcpTool;
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
        self::assertStringContainsString('export type ListAnyProductReportsQuery = AsQuery<AnyProductFilterData>;', $reports);
        self::assertStringContainsString('export type AsQuery<T> = ', $reports);
        // The type itself is as declared, whatever else uses it — a body, a response — where a null
        // is sent and the key is always there.
        self::assertStringContainsString("export interface AnyProductFilterData {\n  product: string | null;\n}", $reports);
    }

    public function test_every_query_is_its_type_as_a_query_string_sends_it(): void
    {
        // Whether a key may be null can rest on a shape this can't see — a base outside the tree —
        // so every query form's type is taken as sent; where none may be null that is the type.
        $reports = $this->emit()['Reports'];

        self::assertStringContainsString('export type ShowReportQuery = AsQuery<ReportFilterData>;', $reports);
        self::assertStringContainsString('export type ListProductReportsQuery = AsQuery<NullableProductFilterData>;', $reports);
        self::assertSame(1, substr_count($reports, 'export type AsQuery<T> = '));
    }

    public function test_an_inherited_key_that_may_be_null_is_one_a_query_may_leave_out(): void
    {
        // The shape it extends is its own interface, whatever else uses it: the query leaves the
        // key out without changing it.
        $reports = $this->emit()['Reports'];

        self::assertStringContainsString('export const ListAnyRegionReports: Endpoint<ListAnyRegionReportsEndpointMap, { query?: ListAnyRegionReportsQuery }> = {', $reports);
        self::assertStringContainsString("export interface RegionScope {\n  region: string | null;\n}", $reports);
        // AsQuery<T> leaves it out at any depth: inside a shape it sends, too.
        self::assertStringContainsString("export interface AnyRegionFilterData extends RegionScope {\n  page?: number;\n  range?: { from: string | null };\n}", $reports);
        self::assertStringContainsString('export type ListAnyRegionReportsQuery = AsQuery<AnyRegionFilterData>;', $reports);
    }

    public function test_a_query_that_narrows_a_key_it_inherits_still_extends_its_base(): void
    {
        // An interface that made its own `region` optional would no longer extend RegionScope.
        $reports = $this->emit()['Reports'];

        self::assertStringContainsString("export interface NorthRegionFilterData extends RegionScope {\n  region: 'north' | null;\n  page?: number;\n}", $reports);
        self::assertStringContainsString('export type ListNorthReportsQuery = AsQuery<NorthRegionFilterData>;', $reports);
        self::assertStringContainsString('export const ListNorthReports: Endpoint<ListNorthReportsEndpointMap, { query?: ListNorthReportsQuery }> = {', $reports);
    }

    public function test_a_query_extending_a_shape_open_to_more_keys_keeps_every_key_it_inherits(): void
    {
        // Omit<> over an index signature keeps nothing but the signature: `product` would be
        // neither required nor a string.
        $reports = $this->emit()['Reports'];

        self::assertStringNotContainsString('Omit<', $reports);
        self::assertStringContainsString("export interface OpenRegionFilterData extends OpenScope {\n  page?: number;\n}", $reports);
        self::assertStringContainsString('export type ShowOpenRegionReportQuery = AsQuery<OpenRegionFilterData>;', $reports);
        self::assertStringContainsString('export const ShowOpenRegionReport: Endpoint<ShowOpenRegionReportEndpointMap, { query: ShowOpenRegionReportQuery }> = {', $reports);
    }

    public function test_a_key_inherited_from_two_levels_is_declared_once(): void
    {
        // Once by each shape that declares it, and not again by the query.
        $reports = $this->emit()['Reports'];

        self::assertStringContainsString("export interface LayeredRegionFilterData extends MidScope {\n  page?: number;\n}", $reports);
        self::assertStringContainsString("export interface MidScope extends DeepScope {\n  region: string | null;\n}", $reports);
        self::assertStringContainsString("export interface DeepScope {\n  region: string | null;\n}", $reports);
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

    public function test_a_key_typed_with_an_alias_that_admits_null_may_be_left_out(): void
    {
        // What the alias admits, the key does — the alias its file declares, one it names that it
        // imports, a project-wide one — so the query a caller may leave out is the one AsQuery<T>
        // makes every key of optional.
        $reports = $this->emit()['Reports'];

        self::assertStringContainsString('export const ListAliasedProductReports: Endpoint<ListAliasedProductReportsEndpointMap, { query?: ListAliasedProductReportsQuery }> = {', $reports);
        self::assertStringContainsString('export const ListImportedProductReports: Endpoint<ListImportedProductReportsEndpointMap, { query?: ListImportedProductReportsQuery }> = {', $reports);
        self::assertStringContainsString('export const ListConfigProductReports: Endpoint<ListConfigProductReportsEndpointMap, { query?: ListConfigProductReportsQuery }> = {', $reports);
        // Read where the shape it inherits the key from declares it.
        self::assertStringContainsString('export const ListAliasedScopeReports: Endpoint<ListAliasedScopeReportsEndpointMap, { query?: ListAliasedScopeReportsQuery }> = {', $reports);
        // One that admits none is still a key a caller must send — `?string` included, a key left out
        // when null, which the alias's TypeScript says as `string`.
        self::assertStringContainsString('export const ShowAliasedRegionReport: Endpoint<ShowAliasedRegionReportEndpointMap, { query: ShowAliasedRegionReportQuery }> = {', $reports);
        self::assertStringContainsString('export type SomeProduct = string;', $reports);
        self::assertStringContainsString('export const ListLeftOutProductReports: Endpoint<ListLeftOutProductReportsEndpointMap, { query: ListLeftOutProductReportsQuery }> = {', $reports);
    }

    public function test_a_query_key_typed_with_an_alias_that_admits_null_is_no_required_tool_argument(): void
    {
        $contracts = (new EndpointContractCollector(typeAliases: ['MaybeText' => 'string | null']))->collect(self::SRC, (new ResponseClassCollector())->collectIndex(self::SRC));

        // A key the query inherits through an intersection is read as its own is: `region: string|null`
        // from RegionScope, a key the server takes left out, is no required argument either; WeeklyReport's
        // inherited `product: string` still is.
        foreach (['listAliasedProductReports' => [], 'listImportedProductReports' => [], 'listConfigProductReports' => [], 'listAliasedScopeReports' => [], 'listAnyRegionReports' => [], 'showAliasedRegionReport' => ['region'], 'listLeftOutProductReports' => ['product'], 'showWeeklyReport' => ['product']] as $method => $required) {
            $query = self::contract($contracts, $method)->request?->query;
            self::assertNotNull($query);
            $tool = (new McpManifestBuilder())->build(['Reports' => [self::asTool(self::contract($contracts, $method), 'GET')]])['tools'][0];
            self::assertSame($required, $tool['inputSchema']['required'] ?? [], $method);
        }
    }

    public function test_a_body_key_typed_with_an_alias_that_admits_null_takes_null(): void
    {
        // An update may clear it, and a list of them may hold one: what the alias admits.
        $contracts = (new EndpointContractCollector())->collect(self::SRC, (new ResponseClassCollector())->collectIndex(self::SRC));
        $tool = (new McpManifestBuilder())->build(['Reports' => [self::asTool(self::contract($contracts, 'updateReportNote'), 'PUT')]])['tools'][0];

        self::assertSame(['type' => ['string', 'null']], $tool['inputSchema']['properties']['note']);
        self::assertSame(['type' => ['string', 'null']], $tool['inputSchema']['properties']['tags']['items']);
        self::assertSame(['type' => ['string', 'null']], $tool['inputSchema']['properties']['code']);
    }

    public function test_as_query_leaves_a_primitive_as_it_is(): void
    {
        // A branded string (`string & {readonly __brand: 'UuidStr'}`) extends object, so a helper that
        // asked that first would map it into an object of every string method.
        $reports = $this->emit()['Reports'];

        self::assertStringContainsString('export type AsQuery<T> = T extends string | number | boolean | bigint | symbol | null | undefined', $reports);
    }

    public function test_a_body_extending_a_shape_takes_the_keys_it_inherits_as_that_shape_declares_them(): void
    {
        // `ReportFields & array{pinned?: bool}`: an update that may send its title, clear its
        // description, or neither — as its base declares, not as the form's `required` guesses.
        $contracts = (new EndpointContractCollector())->collect(self::SRC, (new ResponseClassCollector())->collectIndex(self::SRC));
        $tool = (new McpManifestBuilder())->build(['Reports' => [self::asTool(self::contract($contracts, 'updateReportFields'), 'PUT')]])['tools'][0];

        self::assertArrayNotHasKey('required', $tool['inputSchema']);
        self::assertSame(['type' => 'string'], $tool['inputSchema']['properties']['title']);
        self::assertSame(['type' => ['string', 'null']], $tool['inputSchema']['properties']['description']);
        self::assertSame(['type' => 'boolean'], $tool['inputSchema']['properties']['pinned']);
    }

    /**
     * @param array<string, list<CollectedEndpointContract>> $contracts
     */
    private static function contract(array $contracts, string $method): CollectedEndpointContract
    {
        foreach ($contracts['Reports'] ?? [] as $contract) {
            if ($method === $contract->methodName) {
                return $contract;
            }
        }

        self::fail(\sprintf('No contract for %s.', $method));
    }

    /**
     * The contract as a tool, though its endpoint declares none.
     */
    private static function asTool(CollectedEndpointContract $contract, string $httpMethod): CollectedEndpointContract
    {
        return new CollectedEndpointContract(
            name: $contract->name,
            domain: $contract->domain,
            controllerClass: $contract->controllerClass,
            methodName: $contract->methodName,
            responses: $contract->responses,
            request: $contract->request,
            mcp: new CollectedMcpTool(name: $contract->name, description: 'A report.', httpMethod: $httpMethod, httpPath: '/api/reports', destructive: false),
        );
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
            preserveNull: ['AnyProductFilterData.product', 'RegionScope.region', 'NorthRegionFilterData.region', 'OpenScope.region', 'DeepScope.region', 'MidScope.region', 'ReportFields.description'],
            typeAliases: ['MaybeText' => 'string | null'],
        ))->emit(
            (new PhpDocTypeCollector())->collect(self::SRC),
            $responseCollector->collect(self::SRC),
            (new EndpointContractCollector(typeAliases: ['MaybeText' => 'string | null']))->collect(self::SRC, $responseCollector->collectIndex(self::SRC)),
        );
    }
}
