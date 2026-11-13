<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Mcp;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Config\TypeBridgeConfig;
use PTGS\TypeBridge\Mcp\McpManifestGenerator;
use PTGS\TypeBridge\Parser\NameRefType;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Parser\ShapeType;

/**
 * An embedded form, end to end: keyed by its data class, read whole, left out.
 */
final class EmbeddedFormToolTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/EmbeddedFormFixtures';

    public function test_an_embedded_form_keyed_by_its_class_holds_its_fields_to_that_classs_keys(): void
    {
        // `address?: AddressData`: AddressData's own `_self` says `line2` may be left out.
        self::assertSame(['street'], $this->schema()['properties']['address']['required'] ?? null);
    }

    public function test_a_required_embedded_form_left_out_is_a_new_object_its_not_null_takes(): void
    {
        // Symfony makes a required embedded form left out a new AddressData, which `address`'s
        // NotNull takes; `billing` is not required, so it is null, which its NotNull refuses. A new
        // PostalData has its `street` blank, which its NotBlank refuses where `postal` cascades
        // (#[Assert\Valid]) — not where `shipping` doesn't, nor where `courier`, not required, is
        // null. `memo`'s field's own `constraints` hold left out, the form not required as it is;
        // and `meta`, with no data class, is the array its field is written into, which its
        // NotBlank takes.
        self::assertSame(['billing', 'postal', 'memo'], $this->schema()['required'] ?? null);
    }

    public function test_a_class_level_cascade_cascades_into_what_it_holds(): void
    {
        // ShipData is #[Assert\Cascade]: PostalData's own NotBlank on `street` is validated, so a
        // new PostalData, blank, is refused — as a property's #[Assert\Valid] would have it.
        $tool = (new McpManifestGenerator())->generate(__DIR__ . '/../Fixture/CascadeFixtures', TypeBridgeConfig::fromArray(['clearMissing' => true]))['tools'][0];

        self::assertSame(['postal'], $tool['inputSchema']['required'] ?? null);
    }

    public function test_a_self_its_class_imports_is_its_shape(): void
    {
        // PostalData and RefData import their `_self` from Shapes: `postal`'s `street` may be left
        // out, and `ref`, read whole, is the shape.
        $properties = (new McpManifestGenerator())->generate(__DIR__ . '/../Fixture/ImportedSelfFixtures', new TypeBridgeConfig())['tools'][0]['inputSchema']['properties'];

        self::assertArrayNotHasKey('required', $properties['postal']);
        self::assertSame(['type' => 'object', 'properties' => ['code' => ['type' => 'string']]], $properties['ref']);
    }

    public function test_a_large_contract_with_no_cycle_is_read_through_in_full(): void
    {
        // Sixty keys of a class with twenty alias-typed keys: what each read makes is read once.
        $src = __DIR__ . '/../Fixture/LargeInputFixtures';
        $keys = (new EndpointContractCollector())->collect($src, (new ResponseClassCollector())->statusIndex($src))['Items'][0]->request?->body?->keys()?->fields ?? [];

        self::assertCount(61, $keys);
        self::assertInstanceOf(ShapeType::class, $keys[59]->type);
        self::assertTrue(ShapeField::admitsNull($keys[60]->type), 'memo, last, is still read');
    }

    public function test_what_a_class_reads_as_is_the_same_whichever_input_reads_it_first(): void
    {
        // AData and BData name each other. Read for AData's input first, BData is cut short where it
        // names AData, being read, so it isn't kept: kept, BData's own input would find AData's `b`
        // read through.
        $src = __DIR__ . '/../Fixture/MutualInputFixtures';
        $contracts = (new EndpointContractCollector())->collect($src, (new ResponseClassCollector())->statusIndex($src))['Pairs'];
        $a = $contracts[1]->request?->body?->keys()?->fields[0]->type;

        self::assertInstanceOf(ShapeType::class, $a);
        self::assertInstanceOf(NameRefType::class, $a->fields[0]->type, 'BData, being read, is not read again inside AData');
    }

    public function test_a_read_kept_is_read_back_only_where_what_it_read_through_is_not_being_read(): void
    {
        // NData and KData name each other: XInput reads them from NData, YInput from KData. NData,
        // read for XInput, names only itself at a remove, so it is kept — but read through KData,
        // which YInput is reading: there NData is read afresh and cut short at KData, as KData is
        // for XInput. Each input reads the pair two deep, whichever is read first.
        $src = __DIR__ . '/../Fixture/CrossReadFixtures';
        $contracts = array_column((new EndpointContractCollector())->collect($src, (new ResponseClassCollector())->statusIndex($src))['Links'], null, 'name');
        $n = $contracts['SaveX']->request?->body?->keys()?->fields[0]->type;
        $k = $contracts['SaveY']->request?->body?->keys()?->fields[0]->type;

        self::assertInstanceOf(ShapeType::class, $n);
        self::assertInstanceOf(ShapeType::class, $k);
        $nk = $n->fields[0]->type;
        $kn = $k->fields[0]->type;
        self::assertInstanceOf(ShapeType::class, $nk);
        self::assertInstanceOf(ShapeType::class, $kn);
        self::assertInstanceOf(NameRefType::class, $nk->fields[0]->type, 'NData, being read, is not read again inside KData');
        self::assertInstanceOf(NameRefType::class, $kn->fields[0]->type, 'KData, being read, is not read again inside NData');
    }

    public function test_a_read_where_null_was_not_asked_is_not_one_where_it_is(): void
    {
        // FeeData read for a body no tool takes asks nothing of Money; read for a tool's, it must —
        // which fails the tool, not the TypeScript, which reads neither body's keys.
        $src = __DIR__ . '/../Fixture/AskedReadFixtures';
        self::assertNotEmpty((new EndpointContractCollector(typeAliases: ['Money' => 'Dinero']))->collect($src, (new ResponseClassCollector())->statusIndex($src)));
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Whether the project-wide alias "Money"');

        (new McpManifestGenerator())->generate($src, TypeBridgeConfig::fromArray(['typeAliases' => ['Money' => 'Dinero']]));
    }

    public function test_a_classs_self_is_found_as_its_typescript_finds_it(): void
    {
        // ShippingAddress declares none: its parent's, where `street` may be left out. Hidden's is
        // PHPStan's alone: no shape, so its field is held to its form's `required`. Maybe's `?T` is
        // `T`, as an alias's is: `maybe` can't be left out.
        $src = __DIR__ . '/../Fixture/InheritedSelfFixtures';
        $schema = (new McpManifestGenerator())->generate($src, new TypeBridgeConfig())['tools'][0]['inputSchema'];

        self::assertArrayNotHasKey('required', $schema['properties']['address']);
        self::assertSame(['secret'], $schema['properties']['hidden']['required'] ?? null);
        self::assertSame(['maybe'], $schema['required'] ?? null);
    }

    public function test_a_field_left_out_without_clear_missing_is_its_propertys_none(): void
    {
        // Submitted with `clearMissing: false`, a field left out isn't submitted: no form check runs,
        // its property is null, and its own constraints alone hold — `address`, `billing`, `postal`
        // and `shipping` by their NotNull, `meta` by its NotBlank; `memo` and `courier` by nothing.
        self::assertSame(['address', 'billing', 'postal', 'shipping', 'meta'], $this->schema([], clearMissing: false)['required'] ?? null);

        // And within a form sent: `postal`'s `geo`, a new GeoData where it is submitted blank, is null
        // where it isn't submitted, which its NotNull refuses.
        self::assertSame(['street'], $this->schema()['properties']['postal']['required'] ?? null);
        self::assertSame(['street', 'geo'], $this->schema([], clearMissing: false)['properties']['postal']['required'] ?? null);
    }

    public function test_a_whole_value_field_is_its_shape_an_enums_values_and_an_aliass_type_included(): void
    {
        self::assertSame([
            'type' => 'object',
            'properties' => [
                'status' => ['enum' => ['active', 'archived']],
                'id' => ['type' => 'string'],
                // Keyed by the enum's class: its values, as its TypeScript names them — or, where it
                // names its value union otherwise, its `_self`.
                'kind' => ['enum' => ['active', 'archived']],
                'sort' => ['type' => 'object', 'properties' => ['code' => ['type' => 'string']], 'required' => ['code']],
            ],
            'required' => ['status', 'id'],
        ], $this->schema()['properties']['ref'] ?? null);
    }

    public function test_a_collections_entries_keyed_by_their_class_hold_to_that_classs_keys(): void
    {
        // `phones?: list<PhoneData>`: each entry's `label` may be left out or cleared; `refs?:
        // list<RefData>`, each read whole, is RefData's shape.
        $properties = $this->schema()['properties'];

        self::assertSame(['number'], $properties['phones']['items']['required'] ?? null);
        // PhoneData's own Length is never validated: `phones` doesn't cascade.
        self::assertSame(['type' => 'string'], $properties['phones']['items']['properties']['number'] ?? null);
        self::assertSame(['type' => ['string', 'null']], $properties['phones']['items']['properties']['label'] ?? null);
        self::assertSame($properties['ref'], $properties['refs']['items'] ?? null);
    }

    public function test_classes_naming_each_other_past_what_is_read_fail_their_tool_in_bounded_time(): void
    {
        // Ten input classes each keyed by every other: unrolled on every path, that is 9! reads of
        // each. A name left unread past the bound would publish a tool saying less than its Endpoint
        // — a key its alias lets be cleared, refused — so the tool fails instead, and soon. Its
        // TypeScript reads none of a body's keys, and is generated.
        $this->assertToolFailsInBoundedTime(__DIR__ . '/../Fixture/CliqueFixtures', 'Clique1');
    }

    public function test_aliases_naming_each_other_past_what_is_read_fail_their_tool_in_bounded_time(): void
    {
        // Ten aliases of one class, each with a key of every other.
        $this->assertToolFailsInBoundedTime(__DIR__ . '/../Fixture/AliasCliqueFixtures', 'AliasCliqueData');
    }

    public function test_a_class_naming_itself_is_read_once_wherever_it_is_named(): void
    {
        // Thirty shelves of forty racks of a category, a category naming itself: what each is, it is
        // everywhere, so it is read once — not once a key, 2430 reads, past the bound before `memo`.
        $src = __DIR__ . '/../Fixture/RecursiveFanOutFixtures';
        $contracts = array_column((new EndpointContractCollector())->collect($src, (new ResponseClassCollector())->statusIndex($src))['Stock'], null, 'name');
        $keys = $contracts['Restock']->request?->body?->keys()?->fields ?? [];

        self::assertCount(31, $keys);
        $shelf = $keys[29]->type;
        self::assertInstanceOf(ShapeType::class, $shelf);
        $rack = $shelf->fields[39]->type;
        self::assertInstanceOf(ShapeType::class, $rack);
        self::assertInstanceOf(ShapeType::class, $rack->fields[0]->type);
        self::assertTrue(ShapeField::admitsNull($keys[30]->type), 'memo, last, is read');
    }

    public function test_a_body_no_tool_takes_is_its_contract_as_written(): void
    {
        // Only a tool reads what a body's keys are; its TypeScript names each as written.
        $src = __DIR__ . '/../Fixture/RecursiveFanOutFixtures';
        $contracts = array_column((new EndpointContractCollector())->collect($src, (new ResponseClassCollector())->statusIndex($src))['Stock'], null, 'name');

        self::assertInstanceOf(NameRefType::class, $contracts['PreviewRestock']->request?->body?->keys()?->fields[0]->type);
        self::assertInstanceOf(ShapeType::class, $contracts['Restock']->request?->body?->keys()?->fields[0]->type);
    }

    public function test_a_collector_reads_a_tree_as_a_fresh_one_does_whatever_it_read_before(): void
    {
        // NoteInput's Charge is read in Notes' file in both trees, but its Fee only where the tree
        // holds FeeShapes: read for the whole tree, it isn't what the Notes tree alone makes of it.
        $tree = __DIR__ . '/../Fixture/TwoTreeFixtures';
        $fee = static function (EndpointContractCollector $collector, string $src): ?string {
            $charge = array_values($collector->collect($src, (new ResponseClassCollector())->statusIndex($src)))[0][0]->request?->body?->keys()?->fields[0]->type;

            return $charge instanceof ShapeType ? $charge->fields[0]->type::class : null;
        };
        $collector = new EndpointContractCollector();

        self::assertSame(ShapeType::class, $fee($collector, $tree));
        self::assertSame($fee(new EndpointContractCollector(), $tree . '/Notes'), $fee($collector, $tree . '/Notes'));
    }

    public function test_a_key_typed_with_an_enum_declaring_its_own_shape_is_that_shape(): void
    {
        // NoteStatus serialises to its `_self`, which its TypeScript names NoteStatusData: the key
        // is that shape, in the tool as in the Endpoint, not the enum's values.
        $src = __DIR__ . '/../Fixture/EnumSelfKeyFixtures';
        $status = (new EndpointContractCollector())->collect($src, (new ResponseClassCollector())->statusIndex($src))['Notes'][0]->request?->body?->keys()?->fields[0]->type;

        self::assertInstanceOf(ShapeType::class, $status);
        self::assertSame(['code', 'label'], array_map(static fn (ShapeField $key): string => $key->name, $status->fields));
    }

    public function test_a_class_named_from_its_files_namespace_or_a_use_is_read(): void
    {
        // `Input\AddressData`, `Parts\AddressData` and the name in full are one class, as PHP and the
        // TypeScript resolve them: AddressData's `_self` says `line2` may be left out of each.
        $properties = (new McpManifestGenerator())->generate(__DIR__ . '/../Fixture/PartlyQualifiedFixtures', new TypeBridgeConfig())['tools'][0]['inputSchema']['properties'];

        foreach (['address', 'billing', 'shipping'] as $key) {
            self::assertSame(['street'], $properties[$key]['required'] ?? null, $key);
        }
    }

    public function test_an_inputs_key_naming_its_own_class_stays_a_name(): void
    {
        // NodeInput's `parent` is NodeInput, the class being read: not read again inside itself.
        $src = __DIR__ . '/../Fixture/SelfNamingInputFixtures';
        $keys = (new EndpointContractCollector())->collect($src, (new ResponseClassCollector())->statusIndex($src))['Nodes'][0]->request?->body?->keys();

        self::assertInstanceOf(NameRefType::class, $keys?->fields[1]->type);
    }

    public function test_a_class_naming_itself_is_read_once_round(): void
    {
        // NodeData's `children` are NodeData: what a child is can't be said there, so neither can the
        // tree — an object.
        self::assertSame(['type' => 'object'], $this->schema()['properties']['tree'] ?? null);
    }

    public function test_a_project_wide_alias_of_a_classs_name_is_the_alias(): void
    {
        // `address?: AddressData`, AddressData a config alias too: the alias, as everywhere a name is
        // one — its keys none, so its fields are held to the form's own `required`.
        self::assertSame(['street', 'line2'], $this->schema(['AddressData' => 'string'])['properties']['address']['required'] ?? null);
    }

    private function assertToolFailsInBoundedTime(string $src, string $input): void
    {
        $started = microtime(true);
        self::assertNotEmpty((new EndpointContractCollector())->collect($src, (new ResponseClassCollector())->statusIndex($src)));
        try {
            (new McpManifestGenerator())->generate($src, new TypeBridgeConfig());
            self::fail('Read through without failing.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('name one another in more ways than', $exception->getMessage());
            self::assertStringContainsString($input, $exception->getMessage());
        }
        self::assertLessThan(10.0, microtime(true) - $started);
    }

    /**
     * @param array<string, string> $aliases
     *
     * @return array<string, mixed>
     */
    private function schema(array $aliases = [], bool $clearMissing = true): array
    {
        $config = TypeBridgeConfig::fromArray(['typeAliases' => ['UuidStr' => 'string', ...$aliases], 'clearMissing' => $clearMissing]);

        return (new McpManifestGenerator())->generate(self::SRC, $config)['tools'][0]['inputSchema'];
    }
}
