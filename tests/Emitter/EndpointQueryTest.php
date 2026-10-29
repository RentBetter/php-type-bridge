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

    private const array TYPE_ALIASES = ['MaybeText' => 'string | null', 'ConfigBase' => '{ a?: string }'];

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
        foreach (['listAliasedProductReports' => [], 'listImportedProductReports' => [], 'listConfigProductReports' => [], 'listAliasedScopeReports' => [], 'listAnyRegionReports' => [], 'showAliasedRegionReport' => ['region'], 'listLeftOutProductReports' => ['product'], 'showWeeklyReport' => ['product'], 'listLayeredProductReports' => ['product']] as $method => $required) {
            $query = self::contract($contracts, $method)->request?->query;
            self::assertNotNull($query);
            $tool = (new McpManifestBuilder())->build(['Reports' => [self::asTool(self::contract($contracts, $method), 'GET')]])['tools'][0];
            self::assertSame($required, $tool['inputSchema']['required'] ?? [], $method);
        }
    }

    public function test_a_key_both_sides_type_with_an_alias_that_admits_null_may_be_left_out(): void
    {
        // The base's `note?: MaybeText` and the input's own `note: MaybeText`: required by one side,
        // but admitting null on both, so a query may leave it out — the base's read as its alias
        // says, though nothing asked it alone.
        $contracts = (new EndpointContractCollector(typeAliases: self::TYPE_ALIASES))->collect(self::SRC, (new ResponseClassCollector())->collectIndex(self::SRC));
        $tool = (new McpManifestBuilder())->build(['Reports' => [self::asTool(self::contract($contracts, 'listClearableNoteReports'), 'GET')]])['tools'][0];

        self::assertSame([], $tool['inputSchema']['required'] ?? []);
    }

    public function test_an_alias_whose_null_cant_be_read_is_no_matter_where_its_null_decides_nothing(): void
    {
        // `price?: Money` in a query: left out already, whatever Money admits; a list's items and a
        // map's values, which a query string never sends null; a path's segment. Nothing turns on
        // what tsc would make of `Dinero`.
        $src = __DIR__ . '/../Fixture/OpaqueQueryAliasFixtures';
        $contracts = (new EndpointContractCollector(typeAliases: ['Money' => 'Dinero']))->collect($src, (new ResponseClassCollector())->collectIndex($src));
        $keys = $contracts['Prices'][0]->request?->query?->keys();

        self::assertSame(['title', 'price', 'bands', 'byCurrency', 'floor'], array_map(static fn(ShapeField $key): string => $key->name, $keys->fields ?? []));
        self::assertTrue($keys?->fields[4]->mayBeLeftOut(inQuery: true), '`floor: Money|null`, left out by its explicit null');
        self::assertFalse($keys?->fields[0]->mayBeLeftOut(inQuery: true));
        self::assertTrue($keys?->fields[1]->mayBeLeftOut(inQuery: true));
    }

    public function test_an_alias_whose_null_cant_be_read_is_no_matter_beside_an_explicit_null(): void
    {
        // `price: Money|null` in a body: clearable by its own null, whatever Money admits.
        $src = __DIR__ . '/../Fixture/OpaqueNullableBodyFixtures';
        $contracts = (new EndpointContractCollector(typeAliases: ['Money' => 'Dinero']))->collect($src, (new ResponseClassCollector())->collectIndex($src));

        self::assertTrue(ShapeField::admitsNull($contracts['Prices'][0]->request?->body?->keys()?->fields[0]->type ?? new ShapeType([])));
    }

    public function test_an_alias_whose_null_cant_be_read_is_refused_where_a_body_may_clear_it(): void
    {
        // In a body the same key may be sent null to clear it, or not: that does turn on Money.
        $src = __DIR__ . '/../Fixture/OpaqueBodyAliasFixtures';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Whether the project-wide alias "Money" (`Dinero`) admits null');

        (new EndpointContractCollector(typeAliases: ['Money' => 'Dinero']))->collect($src, (new ResponseClassCollector())->collectIndex($src));
    }

    public function test_a_project_wide_alias_naming_another_is_read_as_that_one_is_declared(): void
    {
        // MaybeText is OptionalText, which admits null: a key typed with it may be left out.
        $aliases = ['MaybeText' => 'OptionalText', 'OptionalText' => 'string | null'];
        $contracts = (new EndpointContractCollector(typeAliases: $aliases))->collect(self::SRC, (new ResponseClassCollector())->collectIndex(self::SRC));
        $tool = (new McpManifestBuilder())->build(['Reports' => [self::asTool(self::contract($contracts, 'listConfigProductReports'), 'GET')]])['tools'][0];

        self::assertSame([], $tool['inputSchema']['required'] ?? []);
    }

    public function test_a_key_typed_with_an_alias_whose_null_its_typescript_cannot_say_is_refused(): void
    {
        // `Nullable<string>` admits null as tsc evaluates it, which a reading of its text can't: the
        // Endpoint's AsQuery<T> and the tool would read the key two ways. Saying so beats guessing.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Whether the project-wide alias "MaybeText" (`Nullable<string>`) admits null');

        (new EndpointContractCollector(typeAliases: ['MaybeText' => 'Nullable<string>']))->collect(self::SRC, (new ResponseClassCollector())->collectIndex(self::SRC));
    }

    public function test_an_input_whose_self_is_an_alias_of_a_shape_has_that_shapes_keys(): void
    {
        // `_self = NoteInput`, NoteInput imported: `title` required and `note` clearable, as that
        // shape declares — not every field required, as the form's default guesses.
        $contracts = (new EndpointContractCollector())->collect(self::SRC, (new ResponseClassCollector())->collectIndex(self::SRC));
        $tool = (new McpManifestBuilder())->build(['Reports' => [self::asTool(self::contract($contracts, 'updateAliasedNote'), 'PUT')]])['tools'][0];

        self::assertSame(['id', 'title'], $tool['inputSchema']['required'], "Its path's id, and the title");
        self::assertSame(['type' => ['string', 'null']], $tool['inputSchema']['properties']['note']);

        // A query whose `_self` is one, with a key it can't leave out, can't be left out either.
        $query = self::asTool(self::contract($contracts, 'showAliasedFilterReport'), 'GET');
        self::assertSame(['product'], (new McpManifestBuilder())->build(['Reports' => [$query]])['tools'][0]['inputSchema']['required'] ?? []);
        self::assertStringContainsString('export const ShowAliasedFilterReport: Endpoint<ShowAliasedFilterReportEndpointMap, { query: ShowAliasedFilterReportQuery }> = {', $this->emit()['Reports']);
    }

    public function test_an_input_whose_self_extends_a_shape_it_cannot_read_still_reads_its_own_keys(): void
    {
        // `_self = LayeredNoteInput`, which extends the config's ConfigBase: what that adds can't be
        // read, and the keys it declares itself are read as declared — `note` clearable.
        $contracts = (new EndpointContractCollector(typeAliases: self::TYPE_ALIASES))->collect(self::SRC, (new ResponseClassCollector())->collectIndex(self::SRC));
        $tool = (new McpManifestBuilder())->build(['Reports' => [self::asTool(self::contract($contracts, 'updateLayeredNote'), 'PUT')]])['tools'][0];

        self::assertSame(['id', 'title'], $tool['inputSchema']['required'], "Its path's id, and the title");
        self::assertSame(['type' => ['string', 'null']], $tool['inputSchema']['properties']['note']);
    }

    public function test_a_key_inherited_through_an_alias_of_an_alias_is_inherited(): void
    {
        // `ListInput & array{product?: string}`, ListInput being PagedInput: `sort` is required, so
        // the query can't be left out, and the tool requires it.
        $contracts = (new EndpointContractCollector())->collect(self::SRC, (new ResponseClassCollector())->collectIndex(self::SRC));
        $tool = (new McpManifestBuilder())->build(['Reports' => [self::asTool(self::contract($contracts, 'listSortedReports'), 'GET')]])['tools'][0];

        self::assertSame(['sort'], $tool['inputSchema']['required'] ?? []);
        self::assertStringContainsString('export const ListSortedReports: Endpoint<ListSortedReportsEndpointMap, { query: ListSortedReportsQuery }> = {', $this->emit()['Reports']);
    }

    public function test_a_body_key_typed_with_an_alias_that_admits_null_takes_null(): void
    {
        // An update may clear it, and a list of them may hold one: what the alias admits.
        $contracts = (new EndpointContractCollector())->collect(self::SRC, (new ResponseClassCollector())->collectIndex(self::SRC));
        $tool = (new McpManifestBuilder())->build(['Reports' => [self::asTool(self::contract($contracts, 'updateReportNote'), 'PUT')]])['tools'][0];

        self::assertSame(['type' => ['string', 'null']], $tool['inputSchema']['properties']['note']);
        self::assertSame(['type' => ['string', 'null']], $tool['inputSchema']['properties']['tags']['items']);
        self::assertSame(['type' => ['string', 'null']], $tool['inputSchema']['properties']['code']);
        self::assertSame(['type' => ['string', 'null']], $tool['inputSchema']['properties']['label'], '`?MaybeProduct`');
    }

    public function test_an_expanded_choice_may_be_cleared(): void
    {
        // Radio buttons are a compound form, but no embedded one: the choice reads what is sent, and
        // a null sent clears it, as Symfony submits it.
        $contracts = (new EndpointContractCollector())->collect(self::SRC, (new ResponseClassCollector())->collectIndex(self::SRC));
        $tool = (new McpManifestBuilder())->build(['Reports' => [self::asTool(self::contract($contracts, 'updateReportStatus'), 'PUT')]])['tools'][0];

        self::assertContains(null, $tool['inputSchema']['properties']['status']['enum'] ?? []);
    }

    public function test_a_key_required_at_any_level_an_input_extends_is_required(): void
    {
        // ProductRoot requires `product`; ProductLayer extending it says `product?:`. The Endpoint and
        // the tool read one set of keys, intersected as TypeScript intersects them.
        $reports = $this->emit()['Reports'];
        self::assertStringContainsString('export const ListLayeredProductReports: Endpoint<ListLayeredProductReportsEndpointMap, { query: ListLayeredProductReportsQuery }> = {', $reports);

        $contracts = (new EndpointContractCollector())->collect(self::SRC, (new ResponseClassCollector())->collectIndex(self::SRC));
        $keys = self::contract($contracts, 'listLayeredProductReports')->request?->query?->keys();
        self::assertNotNull($keys);
        self::assertSame(['product', 'team', 'q'], array_map(static fn(ShapeField $key): string => $key->name, $keys->fields));
        self::assertFalse($keys->fields[0]->mayBeLeftOut(inQuery: true));
    }

    public function test_a_nested_key_typed_with_an_alias_is_read_through_it(): void
    {
        // `address?: ReportAddress`, the alias another class's `_self`: its keys are that shape's —
        // optional, and the street clearable — as they would be written out.
        $contracts = (new EndpointContractCollector())->collect(self::SRC, (new ResponseClassCollector())->collectIndex(self::SRC));
        $tool = (new McpManifestBuilder())->build(['Reports' => [self::asTool(self::contract($contracts, 'updateReportPlace'), 'PATCH')]])['tools'][0];

        $address = $tool['inputSchema']['properties']['address'];
        self::assertArrayNotHasKey('required', $address);
        self::assertSame(['type' => ['string', 'null']], $address['properties']['street']);
        self::assertSame(['type' => 'string'], $address['properties']['city']);

        // `ReportAddress & array{postcode: string}`: its base's keys and its own.
        $billing = $tool['inputSchema']['properties']['billing'];
        self::assertSame(['postcode'], $billing['required']);
        self::assertSame(['type' => ['string', 'null']], $billing['properties']['street']);
    }

    public function test_as_query_sends_a_null_by_leaving_the_key_out_and_no_other_way(): void
    {
        // A query string can't carry a null: a key that may be one is optional, and what it takes
        // when sent is never null — `{product: null}` would be sent as the text "null".
        self::assertStringContainsString('{ [K in keyof T as null extends T[K] ? K : never]?: AsQuery<Exclude<T[K], null>> }', $this->emit()['Reports']);
    }

    public function test_as_query_keeps_a_tuple_a_tuple(): void
    {
        // A tuple is mapped as itself, position by position, not widened to a list of its members.
        self::assertStringContainsString("  : T extends readonly unknown[]\n    ? { [K in keyof T]: AsQuery<T[K]> }", $this->emit()['Reports']);
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

        self::assertSame(['id'], $tool['inputSchema']['required'], 'Its path\'s id alone');
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
            preserveNull: ['AnyProductFilterData.product', 'RegionScope.region', 'NorthRegionFilterData.region', 'OpenScope.region', 'DeepScope.region', 'MidScope.region', 'ReportFields.description', 'ReportAddressInput.street', 'NoteInput.note', 'ReportStatusData.status'],
            typeAliases: self::TYPE_ALIASES,
        ))->emit(
            (new PhpDocTypeCollector())->collect(self::SRC),
            $responseCollector->collect(self::SRC),
            (new EndpointContractCollector(typeAliases: self::TYPE_ALIASES))->collect(self::SRC, $responseCollector->collectIndex(self::SRC)),
        );
    }
}
