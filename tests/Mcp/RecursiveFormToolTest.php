<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Mcp;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Config\TypeBridgeConfig;
use PTGS\TypeBridge\Mcp\McpManifestGenerator;

/**
 * A form that nests itself through a collection — a step's steps, a section's sections — is
 * described to any depth: its entry once, under `$defs`, and each place it nests a reference to it.
 */
final class RecursiveFormToolTest extends TestCase
{
    private const string SRC = __DIR__ . '/../Fixture/RecursiveFormFixtures';

    private const array SECTIONS = ['type' => 'array', 'items' => ['$ref' => '#/$defs/SectionData']];

    public function test_a_collection_of_a_form_nesting_itself_refers_to_its_one_definition(): void
    {
        $schema = $this->schema('CreateOutline');

        self::assertSame(self::SECTIONS, $schema['properties']['sections'] ?? null);
        self::assertSame(['heading', 'sections'], array_keys($schema['$defs']['SectionData']['properties'] ?? []));
        self::assertSame(self::SECTIONS, $schema['$defs']['SectionData']['properties']['sections'] ?? null);
        self::assertSame(['heading'], $schema['$defs']['SectionData']['required'] ?? null);
        self::assertSame(['SectionData'], array_keys($schema['$defs']));
    }

    public function test_a_form_nesting_its_own_root_type_defines_the_root(): void
    {
        $schema = $this->schema('CreateTree');
        $children = ['type' => 'array', 'items' => ['$ref' => '#/$defs/TreeData']];

        self::assertSame(['name', 'children'], array_keys($schema['properties'] ?? []));
        self::assertSame($children, $schema['properties']['children']);
        self::assertSame(['name', 'children'], array_keys($schema['$defs']['TreeData']['properties'] ?? []));
        self::assertSame($children, $schema['$defs']['TreeData']['properties']['children']);
        self::assertSame(['name'], $schema['$defs']['TreeData']['required'] ?? null);
    }

    public function test_an_embedded_form_nesting_itself_is_written_out_and_defined_for_its_nesting(): void
    {
        // `appendix` is one SectionType, not a list: written out where it is, with its own nullable
        // or described key as any embedded form's, and defined for the sections it holds to refer to.
        $schema = $this->schema('CreateBook');

        self::assertSame(self::SECTIONS, $schema['properties']['appendix']['properties']['sections'] ?? null);
        self::assertSame($schema['properties']['appendix'], $schema['$defs']['SectionData'] ?? null);
    }

    public function test_a_query_form_nesting_itself_is_defined_too(): void
    {
        $schema = $this->schema('FindTree');

        self::assertSame(['type' => 'array', 'items' => ['$ref' => '#/$defs/TreeData']], $schema['properties']['children'] ?? null);
        self::assertSame(['name', 'children'], array_keys($schema['$defs']['TreeData']['properties'] ?? []));
    }

    public function test_a_tool_nothing_nests_in_has_no_defs(): void
    {
        $schema = (new McpManifestGenerator())->generate(__DIR__ . '/../Fixture/EmbeddedFormFixtures', TypeBridgeConfig::fromArray([]))['tools'][0]['inputSchema'];

        self::assertArrayNotHasKey('$defs', $schema);
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(string $tool): array
    {
        $tools = (new McpManifestGenerator())->generate(self::SRC, TypeBridgeConfig::fromArray([]))['tools'];
        $byName = array_column($tools, null, 'name');
        self::assertArrayHasKey($tool, $byName);

        return $byName[$tool]['inputSchema'];
    }
}
