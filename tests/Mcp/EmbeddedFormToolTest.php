<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Mcp;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Config\TypeBridgeConfig;
use PTGS\TypeBridge\Mcp\McpManifestGenerator;

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
        // PostalData has its `street` blank, which its NotBlank refuses: `postal` can't be left out.
        self::assertSame(['billing', 'postal'], $this->schema()['required'] ?? null);
    }

    public function test_a_whole_value_field_is_its_shape_an_enums_values_and_an_aliass_type_included(): void
    {
        self::assertSame([
            'type' => 'object',
            'properties' => [
                'status' => ['enum' => ['active', 'archived']],
                'id' => ['type' => 'string'],
                // Keyed by the enum's class: its values, as its TypeScript names them.
                'kind' => ['enum' => ['active', 'archived']],
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
        self::assertSame(['type' => ['string', 'null']], $properties['phones']['items']['properties']['label'] ?? null);
        self::assertSame($properties['ref'], $properties['refs']['items'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        $config = TypeBridgeConfig::fromArray(['typeAliases' => ['UuidStr' => 'string']]);

        return (new McpManifestGenerator())->generate(self::SRC, $config)['tools'][0]['inputSchema'];
    }
}
