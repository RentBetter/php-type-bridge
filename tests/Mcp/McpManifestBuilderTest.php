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
        self::assertSame(['ProjectCreate', 'ProjectDelete'], array_keys($byName));

        self::assertSame('POST', $byName['ProjectCreate']['method']);
        self::assertSame('/api/projects', $byName['ProjectCreate']['path']);
        self::assertArrayHasKey('inputSchema', $byName['ProjectCreate']);

        // Delete's {id} path param is route-derived: Requirement::POSITIVE_INT -> number.
        self::assertSame('DELETE', $byName['ProjectDelete']['method']);
        self::assertSame('/api/projects/{id}', $byName['ProjectDelete']['path']);
        self::assertSame(true, $byName['ProjectDelete']['destructive']);
        self::assertSame([
            'type' => 'object',
            'properties' => ['id' => ['type' => 'number']],
            'required' => ['id'],
        ], $byName['ProjectDelete']['inputSchema']);
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
