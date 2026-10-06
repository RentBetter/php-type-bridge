<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Mcp;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Config\IncludeConvention;
use PTGS\TypeBridge\Mcp\McpManifestBuilder;
use PTGS\TypeBridge\Model\CollectedApiResponseClass;
use PTGS\TypeBridge\Model\CollectedEndpointContract;
use PTGS\TypeBridge\Model\CollectedEndpointRequest;
use PTGS\TypeBridge\Model\CollectedFormField;
use PTGS\TypeBridge\Model\CollectedInputReference;
use PTGS\TypeBridge\Model\CollectedMcpTool;
use PTGS\TypeBridge\Model\CollectedPathParam;
use PTGS\TypeBridge\Parser\ListType;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\ScalarType;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Support\AttributeText;
use PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Common\Spec\Param;
use PTGS\TypeBridge\Tests\Fixture\DescribedMcpFixtures\Common\Spec\Api;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Common\Security\RequiresScope;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Enum\ProjectStatus;
use PTGS\TypeBridge\Tests\Fixture\MultiPropertyScopeFixtures\Common\Security\Authorize;
use PTGS\TypeBridge\Tests\Fixture\MultiPropertyScopeFixtures\Ping\Response\PingResponse;
use Symfony\Component\Validator\Constraints\NotBlank;

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

    public function testNamesTheArgumentsThatGoInTheQueryStringWhateverTheMethod(): void
    {
        $text = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType';
        $contract = new CollectedEndpointContract(
            name: 'runCheck',
            domain: 'checks',
            controllerClass: 'App\\RunController',
            methodName: '__invoke',
            responses: [],
            request: new CollectedEndpointRequest(
                query: new CollectedInputReference(null, 'App\\IncludeData', 'IncludeData', 'checks', [
                    $this->scalarField('include', $text, required: false),
                    $this->scalarField('expand', $text, required: false),
                ]),
                body: new CollectedInputReference(null, 'App\\RunData', 'RunData', 'checks', [
                    $this->scalarField('reason', $text, required: false),
                ]),
            ),
            mcp: new CollectedMcpTool(name: 'runCheck', description: 'Run a check.', httpMethod: 'POST', httpPath: '/checks/run', destructive: false),
        );

        $tool = (new McpManifestBuilder())->build(['checks' => [$contract]])['tools'][0];

        self::assertSame(['include', 'expand'], $tool['query']);
        self::assertSame(['include', 'expand', 'reason'], array_keys($tool['inputSchema']['properties']));
        self::assertArrayNotHasKey('query', (new McpManifestBuilder())->build(['accounts' => [$this->setFeatureContract()]])['tools'][0], 'Only listed when there are any');
    }

    public function testAChoiceFieldPublishesTheValuesItAccepts(): void
    {
        // A model cannot see a PHP enum. Without the values in the schema its only way to learn them
        // is a 422, and a filter it gets wrong reads as "nothing matched" rather than as a mistake.
        $manifest = (new McpManifestBuilder())->build(['projects' => [$this->filterContract(multiple: false)]]);

        self::assertSame(
            ['type' => 'string', 'enum' => ['draft', 'active']],
            $manifest['tools'][0]['inputSchema']['properties']['status'],
        );
    }

    public function testAMultipleChoiceFieldPublishesAListOfThoseValues(): void
    {
        $manifest = (new McpManifestBuilder())->build(['projects' => [$this->filterContract(multiple: true)]]);

        self::assertSame(
            ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['draft', 'active']]],
            $manifest['tools'][0]['inputSchema']['properties']['status'],
        );
    }

    public function testEveryChoiceTypePublishesTheValuesItsBuiltFormAccepts(): void
    {
        // What a request sends is the choice list's values, whatever the choices are: an id where
        // the type matches on one, a backing value under Symfony's EnumType, the choice itself in a
        // plain list. An int-backed enum's values are strings like any other, whatever `_self`
        // declares the key as, since that is what the form reads a submitted one as.
        $properties = $this->argumentTools()['ListChecks']['inputSchema']['properties'];

        self::assertSame(['type' => 'string', 'enum' => ['SECURITY', 'COST']], $properties['area']);
        self::assertSame(['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['security', 'cost']], 'maxItems' => 2], $properties['areas']);
        self::assertSame(['type' => 'string', 'enum' => ['0', '1', '2', '3', '-1']], $properties['rank']);
        self::assertSame(['type' => 'string', 'enum' => ['low', 'high']], $properties['level']);

        // A loader may query, so the choices it would load are not published: its loader throws.
        self::assertSame(['type' => 'string'], $properties['owner']);
    }

    public function testAnAssertChoiceNarrowsTheValuesToTheOnesItAllows(): void
    {
        // On the form or on the data class, its choices are enum cases; what is published is the id
        // each one is sent as. `match: false` names the ones refused, and a Choice in a group of its
        // own narrows nothing a request can count on.
        $properties = $this->argumentTools()['RecordVerdict']['inputSchema']['properties'];

        self::assertSame(['type' => 'string', 'enum' => ['OK', 'WARNING', 'ERROR']], $properties['status']);
        self::assertSame(['type' => 'string', 'enum' => ['OK', 'WARNING', 'ERROR', 'CRITICAL']], $properties['floor']);
    }

    public function testAnArgumentIsBoundedAsItsValidationBoundsIt(): void
    {
        // A model that knows the limits sends a value inside them, rather than learning each from a
        // 422. Both places a constraint is written count, the form's option and the data class's
        // property; of two on one side, the tighter holds. A bound the schema can only misstate is
        // left out: one read off another property, or a length counted in bytes.
        $properties = $this->argumentTools()['RecordVerdict']['inputSchema']['properties'];

        self::assertSame(['type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'exclusiveMaximum' => 4], $properties['retries']);
        self::assertSame(['type' => 'number', 'minimum' => 0.5], $properties['ratio']);
        self::assertSame(['type' => 'string', 'maxLength' => 255, 'minLength' => 3], $properties['note']);

        $readings = $properties['readings'];
        self::assertSame(1, $readings['minItems']);
        self::assertSame(20, $readings['maxItems']);
        self::assertSame(['type' => 'string', 'maxLength' => 80], $readings['items']['properties']['label']);
    }

    public function testAnArgumentIsDescribedByTheParameterAttributeOnItsProperty(): void
    {
        // Read as a tool's own description is, a list joined with a space; an entry's fields are
        // described off the entry's data class. A property without the attribute has no description.
        $properties = $this->argumentTools(new AttributeText(Param::class, null, 'mcpParamDescriptionProperty'))['RecordVerdict']['inputSchema']['properties'];

        self::assertSame(['type' => 'string', 'enum' => ['OK', 'WARNING', 'ERROR'], 'description' => 'The verdict.'], $properties['status']);
        self::assertSame('The lowest status that alerts. Never SKIPPED.', $properties['floor']['description']);
        self::assertSame('The evidence, one entry per figure.', $properties['readings']['description']);
        self::assertSame(['type' => 'string', 'maxLength' => 80, 'description' => 'What was measured.'], $properties['readings']['items']['properties']['label']);
        self::assertSame('The figure as read. With its unit, where it has one.', $properties['readings']['items']['properties']['value']['description']);
        self::assertArrayNotHasKey('description', $properties['note']);
    }

    public function testFailsWhenTheNamedParameterDescriptionPropertyDoesNotExist(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('on "PTGS\\TypeBridge\\Tests\\Fixture\\ArgumentMcpFixtures\\Checks\\Input\\RecordVerdictData::$status" has no property "summary" (named by the mcpParamDescriptionProperty config)');

        $this->argumentTools(new AttributeText(Param::class, 'summary', 'mcpParamDescriptionProperty'));
    }

    public function testSortsToolsByName(): void
    {
        $manifest = (new McpManifestBuilder())->build([
            'd' => [$this->toolContract('zebra')],
            'a' => [$this->toolContract('alpha')],
        ]);

        $names = array_map(static fn (array $tool): mixed => $tool['name'], $manifest['tools']);
        self::assertSame(['alpha', 'zebra'], $names);
        // No scope attribute configured -> no scopes key rather than an empty list.
        self::assertArrayNotHasKey('scopes', $manifest['tools'][0]);
    }

    public function testInheritsTheDescriptionFromTheConfiguredDocumentationAttribute(): void
    {
        // Resolution order, one endpoint each: the tool's own text wins over the attribute's,
        // the attribute's (a list, joined) over the docblock's, and the docblock stands in when
        // neither exists.
        $byName = $this->describedTools(new EndpointContractCollector(mcpDescriptionAttribute: Api::class));

        self::assertSame('Show one ping, per the tool.', $byName['ShowPing']['description']);
        self::assertSame('List the pings. Newest first.', $byName['ListPings']['description']);
        self::assertSame('Count the pings.', $byName['CountPings']['description']);
    }

    public function testWithoutAConfiguredAttributeTheDocblockSummaryStandsIn(): void
    {
        // The documentation attribute is invisible until configured, so ListPings falls through
        // to its docblock; a summary is the text before the first blank line or tag.
        $byName = $this->describedTools(new EndpointContractCollector());

        self::assertSame('Show one ping, per the tool.', $byName['ShowPing']['description']);
        self::assertSame('List the pings, per the docblock.', $byName['ListPings']['description']);
        self::assertSame('Count the pings.', $byName['CountPings']['description']);
    }

    public function testFailsWhenTheNamedDescriptionPropertyDoesNotExist(): void
    {
        $srcDir = __DIR__ . '/../Fixture/DescribedMcpFixtures';
        $responseIndex = (new ResponseClassCollector())->collectIndex($srcDir);
        $collector = new EndpointContractCollector(
            mcpDescriptionAttribute: Api::class,
            mcpDescriptionProperty: 'summary',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('has no property "summary"');

        $collector->collect($srcDir, $responseIndex);
    }

    public function testFailsWhenAnMcpToolHasNoDescriptionFromAnySource(): void
    {
        // An absent key is not an option: a tool the model cannot read is worse than a build
        // break, and a project compiling its manifest into the container sees it there.
        $srcDir = __DIR__ . '/../Fixture/UndescribedMcpFixtures';
        $responseIndex = (new ResponseClassCollector())->collectIndex($srcDir);
        $collector = new EndpointContractCollector();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('has no description');

        $collector->collect($srcDir, $responseIndex);
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

    public function testReadsEveryPublicPropertyWhenNoScopePropertyIsNamed(): void
    {
        // The permissive default, and why mcpScopeProperty exists: a scope attribute that also
        // carries an entity map contributes those class names as scopes, producing a tool gated
        // on a scope no token can ever hold.
        $srcDir = __DIR__ . '/../Fixture/MultiPropertyScopeFixtures';
        $responseIndex = (new ResponseClassCollector())->collectIndex($srcDir);
        $contracts = (new EndpointContractCollector(mcpScopeAttribute: Authorize::class))
            ->collect($srcDir, $responseIndex);

        $manifest = (new McpManifestBuilder())->build($contracts);

        self::assertSame([
            'projects:read',
            PingResponse::class,
        ], $manifest['tools'][0]['scopes']);
    }

    public function testNamingTheScopePropertyReadsOnlyThatProperty(): void
    {
        $srcDir = __DIR__ . '/../Fixture/MultiPropertyScopeFixtures';
        $responseIndex = (new ResponseClassCollector())->collectIndex($srcDir);
        $contracts = (new EndpointContractCollector(
            mcpScopeAttribute: Authorize::class,
            mcpScopeProperty: 'scope',
        ))->collect($srcDir, $responseIndex);

        $manifest = (new McpManifestBuilder())->build($contracts);

        self::assertSame(['projects:read'], $manifest['tools'][0]['scopes']);
    }

    public function testFailsWhenTheNamedScopePropertyDoesNotExist(): void
    {
        $srcDir = __DIR__ . '/../Fixture/MultiPropertyScopeFixtures';
        $responseIndex = (new ResponseClassCollector())->collectIndex($srcDir);
        $collector = new EndpointContractCollector(
            mcpScopeAttribute: Authorize::class,
            mcpScopeProperty: 'permissions',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('has no property "permissions"');

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

    public function testAnEndpointWithNoInputEmitsABareObjectSchema(): void
    {
        // `properties` is omitted rather than emitted empty: a PHP [] encodes as a JSON
        // array, and JSON Schema requires `properties` to be an object.
        $manifest = (new McpManifestBuilder())->build(['misc' => [$this->toolContract('ping')]]);

        self::assertSame(['type' => 'object'], $manifest['tools'][0]['inputSchema']);
    }

    public function testACollectionFieldIsAnArrayOfItsEntryShape(): void
    {
        // A collection has no children of its own at build time, only a prototype: the
        // entry's fields arrive as entryChildren, and a scalar entry has none.
        $contract = new CollectedEndpointContract(
            name: 'recordVerdict',
            domain: 'checks',
            controllerClass: 'App\\RecordController',
            methodName: '__invoke',
            responses: [],
            request: new CollectedEndpointRequest(
                body: new CollectedInputReference(
                    formClass: null,
                    ownerClass: 'App\\RecordVerdictData',
                    typeName: 'RecordVerdictData',
                    domain: 'checks',
                    fields: [
                        $this->collectionField('readings', 'App\\Form\\ReadingType', required: false, entryChildren: [
                            $this->scalarField('label', 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType', required: true),
                            $this->scalarField('baseline', 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType', required: false),
                        ]),
                        $this->collectionField('tags', 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType', required: true),
                    ],
                ),
            ),
            mcp: new CollectedMcpTool(
                name: 'recordVerdict',
                description: 'Record a verdict.',
                httpMethod: 'POST',
                httpPath: '/checks/verdicts',
                destructive: true,
            ),
        );

        $manifest = (new McpManifestBuilder())->build(['checks' => [$contract]]);

        self::assertSame([
            'type' => 'object',
            'properties' => [
                'readings' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'label' => ['type' => 'string'],
                            'baseline' => ['type' => 'string'],
                        ],
                        'required' => ['label'],
                    ],
                ],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['tags'],
        ], $manifest['tools'][0]['inputSchema']);
    }

    /**
     * The DescribedMcpFixtures tools as the given collector resolves them, keyed by name.
     *
     * @return array<string, array<string, mixed>>
     */
    private function describedTools(EndpointContractCollector $collector): array
    {
        $srcDir = __DIR__ . '/../Fixture/DescribedMcpFixtures';
        $contracts = $collector->collect($srcDir, (new ResponseClassCollector())->collectIndex($srcDir));

        $byName = [];
        foreach ((new McpManifestBuilder())->build($contracts)['tools'] as $tool) {
            $name = $tool['name'];
            self::assertIsString($name);
            $byName[$name] = $tool;
        }

        return $byName;
    }

    /**
     * The ArgumentMcpFixtures tools, collected from their forms, keyed by name.
     *
     * @return array<string, array<string, mixed>>
     */
    private function argumentTools(?AttributeText $paramDescriptions = null): array
    {
        $srcDir = __DIR__ . '/../Fixture/ArgumentMcpFixtures';
        $contracts = (new EndpointContractCollector())->collect($srcDir, (new ResponseClassCollector())->collectIndex($srcDir));

        return array_column((new McpManifestBuilder(paramDescriptions: $paramDescriptions))->build($contracts)['tools'], null, 'name');
    }

    /**
     * @param list<CollectedFormField> $entryChildren
     */
    private function collectionField(string $name, string $entryTypeClass, bool $required, array $entryChildren = []): CollectedFormField
    {
        return new CollectedFormField(
            name: $name,
            formTypeClass: 'Symfony\\Component\\Form\\Extension\\Core\\Type\\CollectionType',
            required: $required,
            mapped: true,
            compound: true,
            dataClass: null,
            entryTypeClass: $entryTypeClass,
            entryChildren: $entryChildren,
        );
    }

    private function scalarField(string $name, string $formTypeClass, bool $required): CollectedFormField
    {
        return new CollectedFormField(
            name: $name,
            formTypeClass: $formTypeClass,
            required: $required,
            mapped: true,
            compound: false,
            dataClass: null,
        );
    }

    /**
     * A list endpoint filtered by a status enum — one of them, or several.
     */
    private function filterContract(bool $multiple): CollectedEndpointContract
    {
        return new CollectedEndpointContract(
            name: 'listProjects',
            domain: 'projects',
            controllerClass: 'App\\ListController',
            methodName: '__invoke',
            responses: [],
            request: new CollectedEndpointRequest(
                query: new CollectedInputReference(
                    formClass: null,
                    ownerClass: 'App\\ProjectFilterData',
                    typeName: 'ProjectFilterData',
                    domain: 'projects',
                    fields: [
                        new CollectedFormField(
                            name: 'status',
                            formTypeClass: 'Symfony\\Component\\Form\\Extension\\Core\\Type\\EnumType',
                            required: false,
                            mapped: true,
                            compound: false,
                            dataClass: null,
                            enumClass: ProjectStatus::class,
                            multiple: $multiple,
                            choiceValues: ['draft', 'active'],
                        ),
                    ],
                ),
            ),
            mcp: new CollectedMcpTool(
                name: 'listProjects',
                description: 'The projects.',
                httpMethod: 'GET',
                httpPath: '/projects',
                destructive: false,
            ),
        );
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

    private function toolContract(string $name, ?int $status = null): CollectedEndpointContract
    {
        return new CollectedEndpointContract(
            name: $name,
            domain: 'misc',
            controllerClass: 'App\\Controller',
            methodName: '__invoke',
            responses: null === $status ? [] : [$this->response($status)],
            mcp: new CollectedMcpTool(
                name: $name,
                description: ucfirst($name) . '.',
                httpMethod: 'GET',
                httpPath: '/' . $name,
                destructive: false,
            ),
        );
    }

    /**
     * Every tool whose response has a body takes the include query parameters, described as
     * configured and sent in the query string, without its query form declaring them; one
     * answering 204 takes none, and one whose form declares them gets each once.
     */
    public function testAToolWithAResponseBodyTakesTheIncludeQuery(): void
    {
        $builder = new McpManifestBuilder(new IncludeConvention(query: ['include' => 'Opt-in parts.', 'expand' => 'Records in place.']));

        $tool = $builder->build(['misc' => [$this->toolContract('listThings', status: 200)]])['tools'][0];
        self::assertSame(['include', 'expand'], $tool['query']);
        self::assertSame(['type' => 'string', 'description' => 'Opt-in parts.'], $tool['inputSchema']['properties']['include']);
        self::assertSame(['type' => 'string', 'description' => 'Records in place.'], $tool['inputSchema']['properties']['expand']);

        $noContent = $builder->build(['misc' => [$this->toolContract('deleteThing', status: 204)]])['tools'][0];
        self::assertArrayNotHasKey('query', $noContent);
        self::assertSame(['type' => 'object'], $noContent['inputSchema']);
    }

    public function testAQueryFormThatDeclaresThemStillListsEachOnce(): void
    {
        $text = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType';
        $contract = new CollectedEndpointContract(
            name: 'listThings',
            domain: 'misc',
            controllerClass: 'App\\Controller',
            methodName: '__invoke',
            responses: [$this->response(200)],
            request: new CollectedEndpointRequest(
                query: new CollectedInputReference(null, 'App\\ThingFilter', 'ThingFilter', 'misc', [
                    $this->scalarField('include', $text, required: false),
                    $this->scalarField('name', $text, required: false),
                ]),
            ),
            mcp: new CollectedMcpTool(name: 'listThings', description: 'List things.', httpMethod: 'GET', httpPath: '/things', destructive: false),
        );

        $tool = (new McpManifestBuilder(new IncludeConvention(query: ['include' => 'Opt-in parts.', 'expand' => 'Records in place.'])))->build(['misc' => [$contract]])['tools'][0];

        self::assertSame(['include', 'name', 'expand'], $tool['query']);
        self::assertSame(['include', 'name', 'expand'], array_keys($tool['inputSchema']['properties']));
    }

    public function testAFilterTheContractMakesOptionalIsNotRequiredWhateverItsFormSays(): void
    {
        // A form field is `required` unless it says otherwise, and a filter form rarely does. Read
        // off the form, every filter of a list would be mandatory, and a model could list nothing
        // without inventing a value for each.
        $text = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType';
        $tool = $this->toolTaking(
            fields: [
                $this->scalarField('assignee', $text, required: true),
                $this->scalarField('project', $text, required: true),
            ],
            contract: new ShapeType([
                new ShapeField('assignee', new ScalarType('string'), optional: true),
                new ShapeField('project', new ScalarType('string'), optional: true),
            ]),
        );

        self::assertSame(['assignee', 'project'], array_keys($tool['inputSchema']['properties']));
        self::assertArrayNotHasKey('required', $tool['inputSchema']);
    }

    public function testAKeyTheContractRequiresIsRequiredEvenWhereItsFormIsLenient(): void
    {
        $text = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType';
        $tool = $this->toolTaking(
            fields: [
                $this->scalarField('title', $text, required: false),
                $this->scalarField('notes', $text, required: false),
            ],
            contract: new ShapeType([
                new ShapeField('title', new ScalarType('non-empty-string'), optional: false),
                new ShapeField('notes', new ScalarType('string'), optional: true),
            ]),
        );

        self::assertSame(['title'], $tool['inputSchema']['required']);
    }

    public function testARequestThatMakesSomethingRequiresWhatItsFormRefusesBlank(): void
    {
        // One contract serves a create and an update, so it declares every key optional: right for
        // the update, which changes only what it is sent, and wrong for the create, which refuses a
        // name it isn't given. The field's NotBlank is what says so.
        $text = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType';
        $fields = [
            new CollectedFormField(name: 'name', formTypeClass: $text, required: true, mapped: true, compound: false, dataClass: null, constraints: [new NotBlank()]),
            $this->scalarField('notes', $text, required: true),
        ];
        $contract = new ShapeType([
            new ShapeField('name', new ScalarType('string'), optional: true),
            new ShapeField('notes', new ScalarType('string'), optional: true),
        ]);

        self::assertSame(['name'], $this->toolWithBody('POST', $fields, $contract)['inputSchema']['required']);
        self::assertArrayNotHasKey('required', $this->toolWithBody('PUT', $fields, $contract)['inputSchema']);
    }

    public function testAFieldTheContractDoesNotDeclareIsLeftToItsForm(): void
    {
        $text = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType';
        $fields = [
            $this->scalarField('insisted', $text, required: true),
            $this->scalarField('offered', $text, required: false),
        ];

        // No readable contract at all, and a contract that names neither field: the form decides.
        self::assertSame(['insisted'], $this->toolTaking($fields, contract: null)['inputSchema']['required']);
        self::assertSame(['insisted'], $this->toolTaking($fields, contract: new ShapeType([]))['inputSchema']['required']);
    }

    public function testAScalarIsTheTypeTheContractDeclares(): void
    {
        // A form type's name is a guess at the JSON type: a CheckboxType binds a boolean and says
        // so nowhere in its name, and a custom type says nothing at all.
        $tool = $this->toolTaking(
            fields: [
                $this->scalarField('unscoped', 'Symfony\\Component\\Form\\Extension\\Core\\Type\\CheckboxType', required: true),
                $this->scalarField('page', 'App\\Form\\PageType', required: true),
                $this->scalarField('ratio', 'App\\Form\\RatioType', required: true),
                $this->scalarField('dueFrom', 'App\\Form\\FlexibleDateType', required: true),
                $this->scalarField('search', 'Symfony\\Component\\Form\\Extension\\Core\\Type\\IntegerType', required: true),
                $this->scalarField('tags', 'App\\Form\\CommaSeparatedType', required: true),
            ],
            contract: new ShapeType([
                new ShapeField('unscoped', new ScalarType('bool'), optional: true),
                new ShapeField('page', new ScalarType('positive-int'), optional: true),
                new ShapeField('ratio', new NullableType(new ScalarType('float'), optional: false), optional: true),
                new ShapeField('dueFrom', new ScalarType('string'), optional: true),
                new ShapeField('search', new ScalarType('string'), optional: true),
                // Not a scalar: what a list or a shape looks like is still the form's to say.
                new ShapeField('tags', new ListType(new ScalarType('string')), optional: true),
            ]),
        );

        self::assertSame([
            'unscoped' => ['type' => 'boolean'],
            'page' => ['type' => 'integer'],
            'ratio' => ['type' => 'number'],
            'dueFrom' => ['type' => 'string'],
            'search' => ['type' => 'string'],
            'tags' => ['type' => 'string'],
        ], $tool['inputSchema']['properties']);
    }

    public function testACollectedFilterFormPublishesWhatItsDataClassDeclares(): void
    {
        // End to end over the fixture project: ProjectFiltersData declares three optional keys, one
        // of them a bool bound by a CheckboxType.
        $srcDir = __DIR__ . '/../Fixture/Fixtures';
        $contracts = (new EndpointContractCollector())->collect($srcDir, (new ResponseClassCollector())->collectIndex($srcDir));

        $list = null;
        foreach ($contracts['Projects'] as $contract) {
            if ('listProjectsAction' === $contract->methodName) {
                $list = $contract;
            }
        }
        self::assertNotNull($list);

        $query = $list->request?->query;
        self::assertNotNull($query);
        self::assertNotNull($query->contract);
        self::assertSame(['search', 'page', 'archived'], array_map(static fn (ShapeField $key): string => $key->name, $query->contract->fields));

        $tool = $this->toolTaking($query->fields, $query->contract);
        self::assertSame([
            'search' => ['type' => 'string'],
            'page' => ['type' => 'integer'],
            'archived' => ['type' => 'boolean'],
        ], $tool['inputSchema']['properties']);
        self::assertArrayNotHasKey('required', $tool['inputSchema']);
    }

    /**
     * The tool a list endpoint becomes when its query form has these fields and its data class
     * declares this contract.
     *
     * @param list<CollectedFormField> $fields
     *
     * @return array<string, mixed>
     */
    private function toolTaking(array $fields, ?ShapeType $contract): array
    {
        $endpoint = new CollectedEndpointContract(
            name: 'listTasks',
            domain: 'tasks',
            controllerClass: 'App\\ListController',
            methodName: '__invoke',
            responses: [],
            request: new CollectedEndpointRequest(
                query: new CollectedInputReference(null, 'App\\TaskFilterData', 'TaskFilterData', 'tasks', $fields, $contract),
            ),
            mcp: new CollectedMcpTool(name: 'listTasks', description: 'The tasks.', httpMethod: 'GET', httpPath: '/tasks', destructive: false),
        );

        return (new McpManifestBuilder())->build(['tasks' => [$endpoint]])['tools'][0];
    }

    /**
     * The tool an endpoint becomes when its body form has these fields and its data class declares
     * this contract.
     *
     * @param list<CollectedFormField> $fields
     *
     * @return array<string, mixed>
     */
    private function toolWithBody(string $httpMethod, array $fields, ShapeType $contract): array
    {
        $endpoint = new CollectedEndpointContract(
            name: 'saveProject',
            domain: 'projects',
            controllerClass: 'App\\ProjectController',
            methodName: '__invoke',
            responses: [],
            request: new CollectedEndpointRequest(
                body: new CollectedInputReference(null, 'App\\ProjectData', 'ProjectData', 'projects', $fields, $contract),
            ),
            mcp: new CollectedMcpTool(name: 'saveProject', description: 'Save a project.', httpMethod: $httpMethod, httpPath: '/projects', destructive: true),
        );

        return (new McpManifestBuilder())->build(['projects' => [$endpoint]])['tools'][0];
    }

    private function response(int $status): CollectedApiResponseClass
    {
        return new CollectedApiResponseClass('App\\Response' . $status, 'Response' . $status, 'misc', '', $status, false, []);
    }
}
