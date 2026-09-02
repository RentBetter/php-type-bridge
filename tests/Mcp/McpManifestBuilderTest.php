<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Mcp;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Mcp\McpManifestBuilder;
use PTGS\TypeBridge\Model\CollectedEndpointContract;
use PTGS\TypeBridge\Model\CollectedEndpointRequest;
use PTGS\TypeBridge\Model\CollectedFormField;
use PTGS\TypeBridge\Model\CollectedInputReference;
use PTGS\TypeBridge\Model\CollectedMcpTool;
use PTGS\TypeBridge\Model\CollectedPathParam;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Common\Security\RequiresScope;

final class McpManifestBuilderTest extends TestCase
{
    public function testBuildsToolsOnlyForMcpAnnotatedContractsWithInputSchema(): void
    {
        $manifest = (new McpManifestBuilder())->build([
            'accounts' => [
                $this->setFeatureContract(),
                // A contract with no #[McpTool] must not become a tool.
                new CollectedEndpointContract(
                    name: 'listAccountFeatures',
                    domain: 'accounts',
                    controllerClass: 'App\\ListController',
                    methodName: '__invoke',
                    responses: [],
                ),
            ],
        ]);

        self::assertSame([
            'tools' => [
                [
                    'name' => 'setAccountFeature',
                    'description' => 'Enable or disable a feature for an account.',
                    'method' => 'PUT',
                    'path' => '/admin/accounts/{accountId}/features/{feature}',
                    'destructive' => true,
                    'scopes' => ['admin:features:write'],
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'accountId' => ['type' => 'string'],
                            'feature' => ['type' => 'string'],
                            'enabled' => ['type' => 'boolean'],
                        ],
                        'required' => ['accountId', 'feature', 'enabled'],
                    ],
                ],
            ],
        ], $manifest);
    }

    public function testSortsToolsByNameAndOmitsAbsentDescription(): void
    {
        $manifest = (new McpManifestBuilder())->build([
            'd' => [$this->toolContract('zebra')],
            'a' => [$this->toolContract('alpha')],
        ]);

        $names = array_map(static fn (array $tool): mixed => $tool['name'], $manifest['tools']);
        self::assertSame(['alpha', 'zebra'], $names);
        self::assertArrayNotHasKey('description', $manifest['tools'][0]);
        // No scope attribute configured -> no scopes key rather than an empty list.
        self::assertArrayNotHasKey('scopes', $manifest['tools'][0]);
    }

    public function testCollectsMcpToolsFromAnnotatedFixtureControllers(): void
    {
        $srcDir = __DIR__ . '/../Fixture/Fixtures';
        $responseIndex = (new ResponseClassCollector())->collectIndex($srcDir);
        $contracts = (new EndpointContractCollector())->collect($srcDir, $responseIndex);

        $manifest = (new McpManifestBuilder())->build($contracts);

        $byName = [];
        foreach ($manifest['tools'] as $tool) {
            $name = $tool['name'];
            self::assertIsString($name);
            $byName[$name] = $tool;
        }

        // Opt-in: only the two #[McpTool]-annotated endpoints become tools.
        self::assertSame(['CreateProject', 'DeleteProject'], array_keys($byName));

        self::assertSame('POST', $byName['CreateProject']['method']);
        self::assertSame('/api/projects', $byName['CreateProject']['path']);
        self::assertArrayHasKey('inputSchema', $byName['CreateProject']);

        // Delete's {id} path param is route-derived: Requirement::POSITIVE_INT -> number.
        self::assertSame('DELETE', $byName['DeleteProject']['method']);
        self::assertSame('/api/projects/{id}', $byName['DeleteProject']['path']);
        self::assertSame(true, $byName['DeleteProject']['destructive']);
        self::assertSame([
            'type' => 'object',
            'properties' => ['id' => ['type' => 'number']],
            'required' => ['id'],
        ], $byName['DeleteProject']['inputSchema']);

        // Without a configured scope attribute the fixture scopes are not collected.
        self::assertArrayNotHasKey('scopes', $byName['CreateProject']);
    }

    public function testCollectsScopesFromConfiguredScopeAttribute(): void
    {
        $srcDir = __DIR__ . '/../Fixture/Fixtures';
        $responseIndex = (new ResponseClassCollector())->collectIndex($srcDir);
        $contracts = (new EndpointContractCollector(mcpScopeAttribute: RequiresScope::class))
            ->collect($srcDir, $responseIndex);

        $manifest = (new McpManifestBuilder())->build($contracts);

        $byName = [];
        foreach ($manifest['tools'] as $tool) {
            $name = $tool['name'];
            self::assertIsString($name);
            $byName[$name] = $tool;
        }

        // Class-level scopes come first, then method-level; enum values read as their
        // backed strings, raw strings pass through, duplicates collapse.
        self::assertSame(
            ['projects:read', 'projects:write', 'projects:publish'],
            $byName['CreateProject']['scopes'],
        );

        // Delete has no method-level attribute — the class-level scope alone applies.
        self::assertSame(['projects:read'], $byName['DeleteProject']['scopes']);
    }

    public function testFailsWhenAnMcpToolEndpointLacksTheScopeAttribute(): void
    {
        $srcDir = __DIR__ . '/../Fixture/MissingScopeFixtures';
        $responseIndex = (new ResponseClassCollector())->collectIndex($srcDir);
        $collector = new EndpointContractCollector(mcpScopeAttribute: RequiresScope::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is exposed as an MCP tool but does not declare');

        $collector->collect($srcDir, $responseIndex);
    }

    public function testSkipsClassesThatCannotLoadInThisInstall(): void
    {
        // A project keeps tooling-only classes under src/ — PHPStan rules whose interfaces
        // come from a require-dev package. In a --no-dev image their declaration throws,
        // and a whole-tree scan that let that escape would take the container build down.
        $srcDir = __DIR__ . '/../Fixture/UnloadableFixtures';
        $responseIndex = (new ResponseClassCollector())->collectIndex($srcDir);
        $contracts = (new EndpointContractCollector(mcpScopeAttribute: RequiresScope::class))
            ->collect($srcDir, $responseIndex);

        $manifest = (new McpManifestBuilder())->build($contracts);

        self::assertSame(['Ping'], array_column($manifest['tools'], 'name'));
        self::assertSame(['ping:read'], $manifest['tools'][0]['scopes']);
    }

    private function setFeatureContract(): CollectedEndpointContract
    {
        return new CollectedEndpointContract(
            name: 'setAccountFeature',
            domain: 'accounts',
            controllerClass: 'App\\SetController',
            methodName: '__invoke',
            responses: [],
            request: new CollectedEndpointRequest(
                body: new CollectedInputReference(
                    formClass: null,
                    ownerClass: 'App\\SetAccountFeatureData',
                    typeName: 'SetAccountFeatureData',
                    domain: 'accounts',
                    fields: [
                        new CollectedFormField(
                            name: 'enabled',
                            formTypeClass: 'App\\Form\\BooleanType',
                            required: true,
                            mapped: true,
                            compound: false,
                            dataClass: null,
                        ),
                    ],
                ),
                pathParams: [
                    new CollectedPathParam('accountId', 'string'),
                    new CollectedPathParam('feature', 'string'),
                ],
            ),
            mcp: new CollectedMcpTool(
                name: 'setAccountFeature',
                description: 'Enable or disable a feature for an account.',
                httpMethod: 'PUT',
                httpPath: '/admin/accounts/{accountId}/features/{feature}',
                destructive: true,
                scopes: ['admin:features:write'],
            ),
        );
    }

    private function toolContract(string $name): CollectedEndpointContract
    {
        return new CollectedEndpointContract(
            name: $name,
            domain: 'misc',
            controllerClass: 'App\\Controller',
            methodName: '__invoke',
            responses: [],
            mcp: new CollectedMcpTool(
                name: $name,
                description: null,
                httpMethod: 'GET',
                httpPath: '/' . $name,
                destructive: false,
            ),
        );
    }
}
