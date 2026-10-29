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
        // NotNull takes; `billing` is not required, so it is null, which its NotNull refuses.
        self::assertSame(['billing'], $this->schema()['required'] ?? null);
    }

    public function test_a_whole_value_field_is_its_shape_an_enums_values_and_an_aliass_type_included(): void
    {
        self::assertSame([
            'type' => 'object',
            'properties' => [
                'status' => ['enum' => ['active', 'archived']],
                'id' => ['type' => 'string'],
            ],
            'required' => ['status', 'id'],
        ], $this->schema()['properties']['ref'] ?? null);
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
