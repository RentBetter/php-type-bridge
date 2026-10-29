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
        $tool = (new McpManifestGenerator())->generate(__DIR__ . '/../Fixture/CascadeFixtures', new TypeBridgeConfig())['tools'][0];

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

    public function test_classes_naming_each_other_are_read_in_bounded_time(): void
    {
        // Ten input classes each keyed by every other: unrolled on every path, that is 9! reads of
        // each. Each contract may read as many as the first did.
        $src = __DIR__ . '/../Fixture/CliqueFixtures';
        $started = microtime(true);
        $contracts = (new EndpointContractCollector())->collect($src, (new ResponseClassCollector())->statusIndex($src))['Notes'];

        self::assertLessThan(10.0, microtime(true) - $started);
        self::assertCount(2, $contracts);
        self::assertEquals($contracts[0]->request?->body?->keys(), $contracts[1]->request?->body?->keys());
        self::assertInstanceOf(ShapeType::class, $contracts[1]->request?->body?->keys()?->fields[0]->type);
    }

    public function test_aliases_naming_each_other_are_read_in_bounded_time(): void
    {
        // Ten aliases of one class, each with a key of every other.
        $src = __DIR__ . '/../Fixture/AliasCliqueFixtures';
        $started = microtime(true);
        $contracts = (new EndpointContractCollector())->collect($src, (new ResponseClassCollector())->statusIndex($src))['Notes'];

        self::assertLessThan(10.0, microtime(true) - $started);
        self::assertInstanceOf(ShapeType::class, $contracts[0]->request?->body?->keys()?->fields[0]->type);
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
