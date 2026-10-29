<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Mcp;

use PHPUnit\Framework\Attributes\DataProvider;
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
use PTGS\TypeBridge\Model\FailedBlank;
use PTGS\TypeBridge\Parser\ListType;
use PTGS\TypeBridge\Parser\LiteralType;
use PTGS\TypeBridge\Parser\NameRefType;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Parser\ScalarType;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Parser\UnionType;
use PTGS\TypeBridge\Support\AttributeText;
use PTGS\TypeBridge\Support\FormTypeInspector;
use PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Common\Spec\Param;
use PTGS\TypeBridge\Tests\Fixture\DescribedMcpFixtures\Common\Spec\Api;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Common\Security\RequiresScope;
use PTGS\TypeBridge\Tests\Fixture\Fixtures\Projects\Enum\ProjectStatus;
use PTGS\TypeBridge\Tests\Fixture\MultiPropertyScopeFixtures\Common\Security\Authorize;
use PTGS\TypeBridge\Tests\Fixture\MultiPropertyScopeFixtures\Ping\Response\PingResponse;
use PTGS\TypeBridge\Tests\Fixture\ConstraintFixtures\NoteCode;
use PTGS\TypeBridge\Tests\Fixture\BlankValueFixtures\Notes\Form\NoteType as BlankNoteType;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\AtLeastOneOf;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Validation;

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
        // left out: one read off another property, or a length counted in bytes. A blank the server
        // takes, which a Length skips, is taken too.
        $properties = $this->argumentTools()['RecordVerdict']['inputSchema']['properties'];

        self::assertSame(['type' => 'integer', 'minimum' => 1, 'maximum' => 5, 'exclusiveMaximum' => 4], $properties['retries']);
        self::assertSame(['type' => 'number', 'minimum' => 0.5], $properties['ratio']);
        self::assertSame(['type' => 'string', 'anyOf' => [['maxLength' => 0], ['minLength' => 3]], 'maxLength' => 255], $properties['note']);

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

    public function testAKeyThatTakesAnIdOrTheFieldsToMakeOneIsPublishedAsEither(): void
    {
        // The form binds only the object; a string is lifted out before submit. The contract says
        // both, and a model must be able to send either.
        $text = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType';
        $artefact = new CollectedFormField(
            name: 'artefact',
            formTypeClass: 'App\\EmbeddedArtefactType',
            required: false,
            mapped: true,
            compound: true,
            dataClass: 'App\\ArtefactData',
            children: [$this->scalarField('name', $text, required: true)],
        );
        $contract = new ShapeType([
            new ShapeField('artefact', new UnionType([new ScalarType('string'), new ShapeType([new ShapeField('name', new ScalarType('string'), optional: false)])]), optional: false),
        ]);

        $tool = $this->toolWithBody('POST', [$artefact], $contract);

        self::assertSame(
            ['anyOf' => [['type' => 'string'], ['type' => 'object', 'properties' => ['name' => ['type' => 'string']], 'required' => ['name']]]],
            $tool['inputSchema']['properties']['artefact'],
        );
        self::assertSame(['artefact'], $tool['inputSchema']['required']);
    }

    public function testAFieldWhoseTypeReadsItWholeIsPublishedAsItsDataClassDeclares(): void
    {
        // LabelsType reads what is sent before Symfony maps anything, so its form has no children
        // and once published as a string, which a client checking arguments refused in every form
        // the endpoint accepts. Its data class's `_self` is the only thing that says what it takes —
        // read through the aliases it is written with, the keys it extends included, as the Endpoint
        // reads them.
        $labels = $this->argumentTools()['RecordVerdict']['inputSchema']['properties']['labels'];

        self::assertSame([
            'type' => 'object',
            'properties' => [
                'remove' => ['type' => 'array', 'items' => ['type' => 'string']],
                'set' => ['type' => 'array', 'items' => ['anyOf' => [
                    ['type' => 'string'],
                    ['type' => 'object', 'properties' => ['name' => ['type' => 'string'], 'colour' => ['type' => 'string']], 'required' => ['name']],
                ]]],
                'mode' => ['enum' => ['merge', 'replace']],
                'reason' => ['type' => ['string', 'null']],
            ],
        ], $labels);
    }

    public function testAWholeValueFieldWhoseContractNamesATypeStatedElsewhereIsLeftToItsForm(): void
    {
        // An alias is resolved by the emitter, not here; publishing half a shape would refuse the
        // half it left out.
        $labels = new CollectedFormField(
            name: 'labels',
            formTypeClass: 'App\\LabelsType',
            required: false,
            mapped: true,
            compound: true,
            dataClass: 'App\\LabelOperations',
            contract: new ShapeType([
                new ShapeField('set', new ListType(new NameRefType('Label')), optional: true),
            ]),
        );

        $tool = $this->toolWithBody('PUT', [$labels], new ShapeType([]));

        self::assertSame(['type' => 'string'], $tool['inputSchema']['properties']['labels']);
    }

    public function testALiteralUnionAllowsEachOfItsValues(): void
    {
        // '1' and 1, true and 1: JSON tells each pair apart, and the contract allows both.
        $spec = new CollectedFormField(name: 'spec', formTypeClass: 'App\\SpecType', required: false, mapped: true, compound: true, dataClass: 'App\\Spec', contract: $this->shape("array{code: '1'|1, flag: true|1, level: 1|1, kind: 'a'|int}"));

        $properties = $this->toolWithBody('PUT', [$spec], new ShapeType([]))['inputSchema']['properties']['spec']['properties'];
        self::assertSame(['enum' => ['1', 1]], $properties['code']);
        self::assertArrayHasKey('anyOf', $properties['kind'], 'Not every member a literal: either of them');
        self::assertSame(['enum' => [true, 1]], $properties['flag']);
        self::assertSame(['enum' => [1]], $properties['level']);
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

    public function testABodyKeyTheContractTypesTOrNullTakesNull(): void
    {
        // An update's "clear this": left out, the field keeps its value; sent as null, it is
        // cleared. The contract says so with `T|null` (a project's preserveNull), where `?T` is a
        // key only ever left out — and a model told the key is a plain string could never send it.
        $fields = [
            $this->scalarField('description', 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextareaType', required: false),
            new CollectedFormField(name: 'priority', formTypeClass: 'Symfony\\Component\\Form\\Extension\\Core\\Type\\IntegerType', required: false, mapped: true, compound: false, dataClass: null, constraints: [new Range(min: 1, max: 5)]),
            new CollectedFormField(name: 'size', formTypeClass: 'Symfony\\Component\\Form\\Extension\\Core\\Type\\EnumType', required: false, mapped: true, compound: false, dataClass: null, choiceValues: ['S', 'M', 'L']),
            $this->scalarField('title', 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType', required: false),
        ];
        $contract = new ShapeType([
            new ShapeField('description', new NullableType(new ScalarType('string'), optional: false), optional: true),
            new ShapeField('priority', new NullableType(new ScalarType('int'), optional: false), optional: true),
            new ShapeField('size', new NullableType(new ScalarType('string'), optional: false), optional: true),
            // `?T`: left out, never sent as null.
            new ShapeField('title', new NullableType(new ScalarType('string'), optional: true), optional: true),
        ]);

        self::assertSame([
            'description' => ['type' => ['string', 'null']],
            'priority' => ['type' => ['integer', 'null'], 'minimum' => 1, 'maximum' => 5],
            'size' => ['type' => ['string', 'null'], 'enum' => ['S', 'M', 'L', null]],
            'title' => ['type' => 'string'],
        ], $this->toolWithBody('PUT', $fields, $contract)['inputSchema']['properties']);
    }

    public function testAUnionWithNullAmongSeveralMembersTakesNullToo(): void
    {
        // `'open'|'closed'|null` and `string|array{…}|null` parse to a union, not a nullable: the
        // null is one member of several, and still a value the contract lets be sent.
        $assignee = new CollectedFormField(
            name: 'assignee',
            formTypeClass: 'App\\EmbeddedPersonType',
            required: false,
            mapped: true,
            compound: true,
            dataClass: 'App\\PersonData',
            children: [$this->scalarField('name', 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType', required: true)],
        );
        $status = new CollectedFormField(name: 'status', formTypeClass: 'Symfony\\Component\\Form\\Extension\\Core\\Type\\ChoiceType', required: false, mapped: true, compound: false, dataClass: null, choiceValues: ['open', 'closed']);
        $contract = (new PhpDocShapeParser())->parse("array{status?: 'open'|'closed'|null, assignee?: string|array{name: string}|null}");
        self::assertInstanceOf(ShapeType::class, $contract);

        $properties = $this->toolWithBody('PUT', [$status, $assignee], $contract)['inputSchema']['properties'];

        self::assertSame(['type' => ['string', 'null'], 'enum' => ['open', 'closed', null]], $properties['status']);
        self::assertSame(['anyOf' => [
            ['type' => 'string'],
            ['type' => 'object', 'properties' => ['name' => ['type' => 'string']], 'required' => ['name']],
            ['type' => 'null'],
        ]], $properties['assignee']);
    }

    public function testAKeyItsValidationRefusesNullDoesNotTakeNull(): void
    {
        // One contract serving a create and an update says `string|null`; the field's NotNull, or a
        // NotBlank that doesn't allow null, still refuses it — so a model told null is fine would
        // be sent back a 422.
        $text = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType';
        $field = static fn (string $name, array $constraints): CollectedFormField => new CollectedFormField(name: $name, formTypeClass: $text, required: false, mapped: true, compound: false, dataClass: null, constraints: $constraints);
        $fields = [
            $field('name', [new NotBlank()]),
            $field('code', [new NotNull()]),
            $field('notes', [new NotBlank(allowNull: true)]),
        ];
        $contract = (new PhpDocShapeParser())->parse('array{name?: string|null, code?: string|null, notes?: string|null}');
        self::assertInstanceOf(ShapeType::class, $contract);

        $post = $this->toolWithBody('POST', $fields, $contract)['inputSchema'];

        self::assertSame(['type' => 'string'], $post['properties']['name']);
        self::assertSame(['type' => 'string'], $post['properties']['code']);
        self::assertSame(['type' => ['string', 'null']], $post['properties']['notes']);
        self::assertSame(['name', 'code'], $post['required']);
    }

    public function testAKeyInsideAShapeTakesNullWhereItsContractSaysSo(): void
    {
        // A field read whole publishes the shape its data class declares; a key in that shape typed
        // `T|null` is one an update clears as much as a top-level one, and `?T` is one left out.
        $address = new CollectedFormField(
            name: 'address',
            formTypeClass: 'App\\AddressType',
            required: false,
            mapped: true,
            compound: true,
            dataClass: 'App\\AddressData',
            contract: (new PhpDocShapeParser())->parse("array{line1: string, line2: string|null, region?: ?string, tags?: list<string|null>, kind?: 'home'|'work'|null}"),
        );

        $body = $this->toolWithBody('PUT', [$address], new ShapeType([]))['inputSchema']['properties']['address'];

        self::assertSame([
            'type' => 'object',
            'properties' => [
                'line1' => ['type' => 'string'],
                'line2' => ['type' => ['string', 'null']],
                'region' => ['type' => 'string'],
                'tags' => ['type' => 'array', 'items' => ['type' => ['string', 'null']]],
                'kind' => ['enum' => ['home', 'work', null]],
            ],
            'required' => ['line1', 'line2'],
        ], $body);

        // A query string carries no null, however the shape types its keys.
        $query = $this->toolTaking([$address], new ShapeType([]))['inputSchema']['properties']['address'];
        self::assertSame(['type' => 'string'], $query['properties']['line2']);
        self::assertSame(['type' => 'string'], $query['properties']['tags']['items']);
        self::assertSame(['enum' => ['home', 'work']], $query['properties']['kind']);
    }

    public function testAKeyWhoseValueIsQuestionMarkTIsOptionalAsItsTypeScriptIs(): void
    {
        // `product: ?string` is emitted `product?: string` — left off when null — so the tool must
        // not require what the typed client may leave out.
        $tool = $this->toolTaking(
            fields: [$this->scalarField('product', 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType', required: true)],
            contract: (new PhpDocShapeParser())->parse('array{product: ?string}'),
        );

        self::assertArrayNotHasKey('required', $tool['inputSchema']);
    }

    public function testAQueryKeyTypedTOrNullStaysItsTypeForAQueryStringCannotSendNull(): void
    {
        $tool = $this->toolTaking(
            fields: [$this->scalarField('ratio', 'App\\Form\\RatioType', required: false)],
            contract: new ShapeType([new ShapeField('ratio', new NullableType(new ScalarType('float'), optional: false), optional: true)]),
        );

        self::assertSame(['type' => 'number'], $tool['inputSchema']['properties']['ratio']);
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function routeRequirements(): iterable
    {
        // Published: what reads the same, and compiles, as Unicode-mode ECMA-262 — anchored, since
        // JSON Schema's `pattern` matches anywhere and a route matches its segment whole.
        yield 'a code' => ['[A-Z]{1,5}-[0-9]+', '^(?:[A-Z]{1,5}-[0-9]+)$'];
        yield 'a uuid' => ['[0-9a-f]{8}-[0-9a-f]{4}-[13-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}', '^(?:[0-9a-f]{8}-[0-9a-f]{4}-[13-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})$'];
        yield 'digits' => ['\d{4}', '^(?:\d{4})$'];
        yield 'a group and an alternation' => ['(?:draft|live)-\d+', '^(?:(?:draft|live)-\d+)$'];
        yield 'escaped syntax' => ['v\d+\.\d+', '^(?:v\d+\.\d+)$'];
        yield 'a dash at a class edge' => ['[a-z0-9-]+', '^(?:[a-z0-9-]+)$'];
        // As the router holds it — the collector has taken the anchors off, once — so an escaped
        // dollar at its end is the character, not an anchor to strip again.
        yield 'an escaped dollar at the end' => ['a\$', '^(?:a\$)$'];
        yield 'a lazy quantifier' => ['[a-z]+?x', '^(?:[a-z]+?x)$'];
        yield 'an open-ended count' => ['\d{2,}', '^(?:\d{2,})$'];
        yield 'a dash first and last in a class' => ['[-a-z][a-z-]', '^(?:[-a-z][a-z-])$'];
        yield 'an escaped dash and a class escape in a class' => ['[\-\d_]+', '^(?:[\-\d_]+)$'];
        yield 'a backspace in a class' => ['[\b]', '^(?:[\b])$'];
        yield 'an empty alternative' => ['(?:a|)b', '^(?:(?:a|)b)$'];

        // Not published: PCRE's, or an error in Unicode mode, or a different rule there.
        yield 'an inline flag' => ['(?i)[a-z]+', null];
        yield 'a lookbehind' => ['(?<!x)y', null];
        yield 'a possessive quantifier' => ['a++', null];
        yield 'an escaped underscore' => ['user\_[a-z]+', null];
        yield 'an escaped dash outside a class' => ['[A-Z]+\-[0-9]+', null];
        yield 'a backreference' => ['(a)\1', null];
        yield 'an octal escape' => ['\012', null];
        yield 'a range from a class escape' => ['[\w-\.]+', null];
        yield 'a lone brace' => ['{[a-z]+}', null];
        yield 'an open-ended brace' => ['a{,3}', null];
        yield 'a POSIX class' => ['[[:alpha:]]+', null];
        yield 'a negated POSIX class' => ['[^[:space:]]+', null];
        yield 'a POSIX class mid-bracket' => ['[a-z[:digit:]]+', null];
        yield 'a Unicode property' => ['\p{L}+', null];
        yield 'a hex escape PCRE-style' => ['\x{41}', null];
        // \s takes NBSP and U+2028 in Unicode-mode ECMA, not in PCRE; PCRE's \v is every vertical
        // space, ECMA's only U+000B.
        yield 'whitespace' => ['\S+', null];
        yield 'vertical space' => ['[^\v]+', null];
        // A route's dot matches a newline (Symfony compiles with `s`); ECMA's never does.
        yield 'a dot' => ['a.b', null];
        // Resolved by the router from the container: the literal text is not the rule.
        yield 'a container parameter' => ['%app.locales%', null];
        yield 'an env placeholder' => ['%env(LOCALES)%', null];
        yield 'non-ASCII' => ['[a-zé]+', null];
        yield 'a count out of order' => ['a{5,2}', null];
        yield 'a range out of order' => ['[z-a]', null];
        yield 'an empty class' => ['[]a]', null];
        yield 'an empty negated class' => ['[^]a]', null];
        yield 'a quantifier on a quantifier' => ['a**', null];
        yield 'a quantifier on a word boundary' => ['\b+', null];
        yield 'a quantifier with nothing before it' => ['*a', null];
        yield 'a quantifier at a group start' => ['(*a)', null];
        yield 'an unclosed group' => ['(a', null];
        yield 'an unopened group' => ['a)', null];
        yield 'a lookahead' => ['(?=a)b', null];
        yield 'a range to a class escape' => ['[a-\d]', null];
        yield 'an unclosed class' => ['[a-z', null];
        yield 'a trailing backslash' => ['a\\', null];
    }

    #[DataProvider('routeRequirements')]
    public function testAStringPathSegmentPublishesItsRouteRequirementOnlyWhereJsonSchemaReadsItTheSame(string $requirement, ?string $pattern): void
    {
        // A route without `utf8`: its requirement is matched byte by byte, as ECMA would match it
        // only where nothing in it can match a byte of a multibyte character.
        $schema = $this->pathTool([new CollectedPathParam('segment', 'string', $requirement, utf8: false)])['segment'];

        self::assertSame(null === $pattern ? ['type' => 'string'] : ['type' => 'string', 'pattern' => $pattern], $schema);
    }

    /**
     * @return iterable<string, array{string, ?bool, ?string}>
     */
    public static function utf8Requirements(): iterable
    {
        // Without `utf8` PCRE matches bytes: what can match a byte of a multibyte character — a
        // negated class, \D, \W — reads differently from ECMA, which matches code points.
        yield 'bytes: a negated class' => ['[^/]+', false, null];
        yield 'bytes: \W' => ['a\Wb', false, null];
        yield 'bytes: digits' => ['\d{4}', false, '^(?:\d{4})$'];
        // With it, PCRE's \d \w \b are Unicode (UCP); ECMA's stay ASCII.
        yield 'utf8: a negated class' => ['[^/]+', true, '^(?:[^/]+)$'];
        yield 'utf8: digits' => ['\d{4}', true, null];
        yield 'utf8: a word' => ['\w+', true, null];
        yield 'utf8: ASCII ranges' => ['[a-z0-9]+', true, '^(?:[a-z0-9]+)$'];
        // Not stated on the route — the router's default decides — so only what reads the same both ways.
        yield 'unknown: a negated class' => ['[^/]+', null, null];
        yield 'unknown: digits' => ['\d{4}', null, null];
        yield 'unknown: ASCII ranges' => ['[a-z0-9]+', null, '^(?:[a-z0-9]+)$'];
    }

    #[DataProvider('utf8Requirements')]
    public function testARouteRequirementReadsAsItsUtf8OptionMatchesIt(string $requirement, ?bool $utf8, ?string $pattern): void
    {
        $schema = $this->pathTool([new CollectedPathParam('segment', 'string', $requirement, utf8: $utf8)])['segment'];

        self::assertSame(null === $pattern ? ['type' => 'string'] : ['type' => 'string', 'pattern' => $pattern], $schema);
    }

    public function testANumberOrUnrequiredSegmentHasNoPattern(): void
    {
        self::assertSame(
            ['checkNumber' => ['type' => 'number'], 'anything' => ['type' => 'string']],
            $this->pathTool([new CollectedPathParam('checkNumber', 'number', '[0-9]+'), new CollectedPathParam('anything', 'string')]),
        );
    }

    /**
     * @return iterable<string, array{list<Constraint>, ?string}>
     */
    public static function regexConstraints(): iterable
    {
        // PCRE's `$` also matches before a final newline (no D flag), so the published rule allows
        // one; with D it doesn't.
        yield 'anchored' => [[new Regex('/^[A-Z]{1,5}$/')], '^(?:[A-Z]{1,5})\n?$'];
        yield 'anchored, D' => [[new Regex('/^[A-Z]{1,5}$/D')], '^(?:[A-Z]{1,5})$'];
        yield 'a grouped alternation' => [[new Regex('/^(none|[A-Z]{1,5}-G[0-9]+)$/')], '^(?:(none|[A-Z]{1,5}-G[0-9]+))\n?$'];
        yield 'another delimiter' => [[new Regex('#^[a-z]+/[a-z]+$#')], '^(?:[a-z]+/[a-z]+)\n?$'];
        yield 'an escaped delimiter' => [[new Regex('/^[a-z]+\/[a-z]+$/D')], '^(?:[a-z]+\/[a-z]+)$'];
        yield 'escaped syntax' => [[new Regex('/^[a-z]+\.[a-z]+$/D')], '^(?:[a-z]+\.[a-z]+)$'];
        yield 'the Unicode flag' => [[new Regex('/^[a-z]+$/uD')], '^(?:[a-z]+)$'];
        // Without `u` PCRE matches bytes; with it, its \d \w \b are Unicode. Each is published only
        // where it reads the same as ECMA's code points and ASCII classes.
        yield 'bytes: digits' => [[new Regex('/^\d{4}$/D')], '^(?:\d{4})$'];
        yield 'bytes: a word' => [[new Regex('/^\w+$/D')], '^(?:\w+)$'];
        yield 'bytes: a negated class' => [[new Regex('/^[^<>]{1,3}$/')], null];
        yield 'bytes: \W' => [[new Regex('/^\W$/')], null];
        yield 'bytes: \D' => [[new Regex('/^\D+$/')], null];
        yield 'utf8: a negated class' => [[new Regex('/^[^<>]{1,3}$/uD')], '^(?:[^<>]{1,3})$'];
        yield 'utf8: a word' => [[new Regex('/^\w+$/u')], null];
        yield 'utf8: digits' => [[new Regex('/^\d{4}$/u')], null];
        yield 'utf8: a word boundary' => [[new Regex('/^a\b$/u')], null];
        yield 'bracket delimiters' => [[new Regex('{^[a-z]+$}')], '^(?:[a-z]+)\n?$'];
        yield 'an escaped delimiter that is no syntax' => [[new Regex('#^a\#b$#D')], '^(?:a#b)$'];
        // PCRE reads the body as written, escaped delimiter and all: `\-` stays a literal dash in a
        // class, as Unicode mode reads it too, and `\,` a literal comma, which Unicode mode refuses —
        // where unescaping would make a range or a quantifier of them.
        yield 'a dash delimiter, escaped in a class' => [[new Regex('-^[a\-z]+$-D')], '^(?:[a\-z]+)$'];
        yield 'a comma delimiter, escaped in a count' => [[new Regex(',^a{2\,3}$,D')], null];
        yield 'an escaped backslash before the end' => [[new Regex('/^a\\\\$/D')], '^(?:a\\\\)$'];

        // Matched anywhere in PCRE; padding it with `.*` would refuse a newline PCRE crosses.
        yield 'unanchored' => [[new Regex('/[a-z]+-[a-z]+/')], null];
        yield 'anchored at the start only' => [[new Regex('/^abc/')], null];
        yield 'anchored at the end only' => [[new Regex('/abc$/')], null];
        yield 'an escaped dollar, not an anchor' => [[new Regex('/^\d+\$/')], null];
        // `^a|b$` is `(^a)|(b$)`; wrapped whole it would be another rule.
        yield 'an alternation outside any group' => [[new Regex('/^a|b$/')], null];
        yield 'a case flag' => [[new Regex('/^[a-z]+$/i')], null];
        yield 'whitespace' => [[new Regex('/^\S+$/')], null];
        yield 'a dot' => [[new Regex('/^a.b$/')], null];
        // The validator matches the normalised value; the client would check what is sent.
        yield 'a normaliser' => [[new Regex(pattern: '/^[A-Z]{2,5}$/', normalizer: 'trim')], null];
        yield 'must not match' => [[new Regex(pattern: '/^[0-9]+$/', match: false)], null];
        // Both must hold, which one `pattern` cannot say.
        yield 'two' => [[new Regex('/^[A-Z]+$/'), new Regex('/^.{2,5}$/')], null];
        // A rule nested where it always holds — in a sequence, in a project's compound rule — is the
        // field's as much as one written beside the others; one among alternatives may not hold.
        yield 'one, in a sequence' => [[new Sequentially([new Regex('/^[A-Z]{1,5}$/D')])], '^(?:[A-Z]{1,5})$'];
        yield 'one, in a compound rule' => [[new NoteCode()], '^(?:[A-Z]+)$'];
        yield 'two, one of them in a sequence' => [[new Regex('/^[A-Z]+$/D'), new Sequentially([new Regex('/^[A-Z]{2,5}$/D')])], null];
        yield 'two, one of them among alternatives' => [[new Regex('/^[A-Z]+$/D'), new AtLeastOneOf([new Regex('/^[0-9]+$/D'), new Length(max: 3)])], null];
        yield 'one, among alternatives' => [[new AtLeastOneOf([new Regex('/^[A-Z]+$/D'), new Length(max: 3)])], null];
        yield 'a letter for a delimiter' => [[new Regex('a^b$a')], null];
        // A range may start at a dash; one out of order compiles in neither dialect.
        yield 'a range from a dash' => [[new Regex('/^[--0]+$/D')], '^(?:[--0]+)$'];
        yield 'a range from a dash, out of order' => [[new Regex('/^[--!]+$/D')], null];
        yield 'an extended flag' => [[new Regex('/^[a-z]+$/x')], null];
    }

    /**
     * @param list<Constraint> $constraints
     */
    #[DataProvider('regexConstraints')]
    public function testAStringsRegexConstraintIsItsPatternOnlyWhereJsonSchemaReadsItTheSame(array $constraints, ?string $pattern): void
    {
        // Untrimmed and refused blank, the value the Regex sees is the one sent: the pattern is the
        // rule alone. Trimming and blank values are testAStringsRegexAllowsWhatTheFormAndValidatorAllow.
        $field = new CollectedFormField(
            name: 'code',
            formTypeClass: 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType',
            required: false,
            mapped: true,
            compound: false,
            dataClass: null,
            constraints: [...$constraints, new NotBlank()],
            trim: false,
        );

        $schema = $this->toolWithBody('PUT', [$field], new ShapeType([]))['inputSchema']['properties']['code'];

        self::assertSame(null === $pattern ? ['type' => 'string'] : ['type' => 'string', 'pattern' => $pattern], $schema);
    }

    /**
     * @return iterable<string, array{bool, list<Constraint>, string, ?string}>
     */
    public static function normalisedValues(): iterable
    {
        // The form trims what it is sent (`trim`, on by default) before the Regex sees it. The
        // pattern is the rule as it reads the trimmed value: a padded value the server would trim
        // is refused, which costs a caller nothing, and the pattern needs nothing — no `\p{…}` —
        // that only Unicode-mode regex reads. A blank value the form hands on as null, trimmed or
        // not: the Regex lets that through, and only a NotNull or a NotBlank that doesn't allow
        // null refuses it.
        yield 'trimmed, blank allowed' => [true, [], '/^[A-Z]{2,5}$/', '^(?:[A-Z]{2,5})?$'];
        yield 'trimmed, NotBlank' => [true, [new NotBlank()], '/^[A-Z]{2,5}$/', '^(?:[A-Z]{2,5})$'];
        yield 'trimmed, NotNull' => [true, [new NotNull()], '/^[A-Z]{2,5}$/', '^(?:[A-Z]{2,5})$'];
        yield 'trimmed, NotBlank allowing null' => [true, [new NotBlank(allowNull: true)], '/^[A-Z]{2,5}$/', '^(?:[A-Z]{2,5})?$'];
        // Trimmed, PCRE's `$` has no final newline left to allow.
        yield 'trimmed, D' => [true, [new NotBlank()], '/^[A-Z]{2,5}$/D', '^(?:[A-Z]{2,5})$'];
        yield 'as sent, blank allowed' => [false, [], '/^[A-Z]{2,5}$/', '^(?:(?:[A-Z]{2,5})\n?)?$'];
        yield 'as sent, blank allowed, D' => [false, [], '/^[A-Z]{2,5}$/D', '^(?:[A-Z]{2,5})?$'];
        yield 'as sent, NotBlank' => [false, [new NotBlank()], '/^[A-Z]{2,5}$/', '^(?:[A-Z]{2,5})\n?$'];
        yield 'as sent, NotNull' => [false, [new NotNull()], '/^[A-Z]{2,5}$/', '^(?:[A-Z]{2,5})\n?$'];
        yield 'as sent, NotBlank allowing null' => [false, [new NotBlank(allowNull: true)], '/^[A-Z]{2,5}$/', '^(?:(?:[A-Z]{2,5})\n?)?$'];
        // A rule that can match what the form trims can't be told apart from the padding.
        yield 'trimmed, the rule matches a space' => [true, [new NotBlank()], '/^[a-z ]+$/', null];
        yield 'trimmed, the rule matches a tab' => [true, [new NotBlank()], '/^a\tb$/', null];
        yield 'trimmed, the rule has a negated class' => [true, [new NotBlank()], '/^[^<>]+$/u', null];
        yield 'as sent, the rule matches a space' => [false, [new NotBlank()], '/^[a-z ]+$/', '^(?:[a-z ]+)\n?$'];
    }

    /**
     * @param list<Constraint> $constraints
     */
    #[DataProvider('normalisedValues')]
    public function testAStringsRegexAllowsWhatTheFormAndValidatorAllow(bool $trim, array $constraints, string $regex, ?string $pattern): void
    {
        $field = new CollectedFormField(
            name: 'code',
            formTypeClass: 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType',
            required: false,
            mapped: true,
            compound: false,
            dataClass: null,
            constraints: [new Regex($regex), ...$constraints],
            trim: $trim,
        );

        $schema = $this->toolWithBody('PUT', [$field], new ShapeType([]))['inputSchema']['properties']['code'];

        self::assertSame(null === $pattern ? ['type' => 'string'] : ['type' => 'string', 'pattern' => $pattern], $schema);
    }

    /**
     * @return iterable<string, array{list<Constraint>, string}>
     */
    public static function emptyStringValues(): iterable
    {
        // A text field whose `empty_data` is '' keeps a blank value a string: the Regex lets ''
        // through, so does a NotNull, and any NotBlank refuses it.
        yield 'nothing else' => [[], '^(?:[A-Z]+)?$'];
        yield 'NotNull' => [[new NotNull()], '^(?:[A-Z]+)?$'];
        yield 'NotBlank' => [[new NotBlank()], '^(?:[A-Z]+)$'];
        yield 'NotBlank allowing null' => [[new NotBlank(allowNull: true)], '^(?:[A-Z]+)$'];
    }

    /**
     * @param list<Constraint> $constraints
     */
    #[DataProvider('emptyStringValues')]
    public function testABlankValueKeptAnEmptyStringIsAllowedWhereItsConstraintsTakeOne(array $constraints, string $pattern): void
    {
        $field = new CollectedFormField(name: 'code', formTypeClass: 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType', required: false, mapped: true, compound: false, dataClass: null, constraints: [new Regex('/^[A-Z]+$/'), ...$constraints], blankValue: '');

        self::assertSame(['type' => 'string', 'pattern' => $pattern], $this->toolWithBody('PUT', [$field], new ShapeType([]))['inputSchema']['properties']['code']);
    }

    public function testAFieldThatKeepsABlankStringIsRequiredAndTakesNoNullWhereANotBlankRefusesIt(): void
    {
        // Left out or sent as null, it reaches validation as '' — which NotBlank(allowNull: true)
        // refuses, though it would let a null through.
        $text = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType';
        $title = new CollectedFormField(name: 'title', formTypeClass: $text, required: false, mapped: true, compound: false, dataClass: null, constraints: [new NotBlank(allowNull: true)], blankValue: '');
        $note = new CollectedFormField(name: 'note', formTypeClass: $text, required: false, mapped: true, compound: false, dataClass: null, constraints: [new NotNull()], blankValue: '');
        $contract = (new PhpDocShapeParser())->parse('array{title?: string|null, note?: string|null}');
        self::assertInstanceOf(ShapeType::class, $contract);

        $tool = $this->toolWithBody('POST', [$title, $note], $contract);
        self::assertSame(['title'], $tool['inputSchema']['required']);
        self::assertSame(['type' => 'string'], $tool['inputSchema']['properties']['title']);
        // A NotNull takes the '' a null becomes.
        self::assertSame(['type' => ['string', 'null']], $tool['inputSchema']['properties']['note']);
    }

    /**
     * @return iterable<string, array{mixed, Constraint, bool}>
     */
    public static function blankValues(): iterable
    {
        // A field left out of a request or sent null reaches validation as its form makes it: a
        // collection's or a multiple choice's an empty list, a checkbox's false, a text field's that
        // keeps a blank a string ''. Each refused by more than a NotNull or a NotBlank.
        yield 'an empty list, a NotBlank allowing null' => [[], new NotBlank(allowNull: true), true];
        yield 'an empty list, a Count' => [[], new Count(min: 1), true];
        yield 'an empty list, a NotNull' => [[], new NotNull(), false];
        yield 'an unticked checkbox, a NotBlank allowing null' => [false, new NotBlank(allowNull: true), true];
        yield 'an unticked checkbox, an IsTrue' => [false, new IsTrue(), true];
        yield 'an unticked checkbox, a NotNull' => [false, new NotNull(), false];
        yield "'', a Length" => ['', new Length(min: 1), true];
        yield "'', a Choice" => ['', new Choice(choices: ['open', 'closed']), true];
        yield 'null, a Length' => [null, new Length(min: 1), false];
        yield 'null, a NotBlank allowing it' => [null, new NotBlank(allowNull: true), false];
    }

    #[DataProvider('blankValues')]
    public function testWhatAFieldsConstraintsAreAskedAboutIsTheBlankItsFormMakes(mixed $blank, Constraint $constraint, bool $refused): void
    {
        $field = new CollectedFormField(name: 'value', formTypeClass: 'App\\ValueType', required: false, mapped: true, compound: false, dataClass: null, constraints: [$constraint], blankValue: $blank);

        $tool = $this->toolWithBody('POST', [$field], $this->shape('array{value?: string|null}'));
        // A request that makes something can't go without what its form refuses blank; and a null
        // sent is that same blank, published only where it is taken.
        self::assertSame($refused ? ['value'] : [], $tool['inputSchema']['required'] ?? []);
        self::assertSame($refused ? 'string' : ['string', 'null'], $tool['inputSchema']['properties']['value']['type']);
    }

    public function testAnEntrysBlankIsTheOneItsFormMakesToo(): void
    {
        $collection = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\CollectionType';
        $checkbox = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\CheckboxType';
        $flags = new CollectedFormField(name: 'flags', formTypeClass: $collection, required: false, mapped: true, compound: true, dataClass: null, entryTypeClass: $checkbox, entryConstraints: [new NotBlank(allowNull: true)], entryBlankValue: false);

        $body = $this->toolWithBody('PUT', [$flags], $this->shape('array{flags?: list<bool|null>}'))['inputSchema']['properties'];
        self::assertSame(['type' => 'boolean'], $body['flags']['items']);
    }

    public function testAConstraintNestedWhereItAlwaysHoldsIsHeldToo(): void
    {
        // A sequence's NotBlank refuses a blank as much as one written beside it; and its Length
        // bounds the value as much.
        $text = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType';
        $name = new CollectedFormField(name: 'name', formTypeClass: $text, required: false, mapped: true, compound: false, dataClass: null, constraints: [new Sequentially([new NotBlank(), new Length(max: 40)])]);
        $code = new CollectedFormField(name: 'code', formTypeClass: $text, required: false, mapped: true, compound: false, dataClass: null, constraints: [new NoteCode()], trim: false);

        $tool = $this->toolWithBody('POST', [$name, $code], $this->shape('array{name?: string|null, code?: string|null}'));
        self::assertSame(['name', 'code'], $tool['inputSchema']['required']);
        self::assertSame(['type' => 'string', 'maxLength' => 40], $tool['inputSchema']['properties']['name']);
        self::assertSame(['type' => 'string', 'pattern' => '^(?:[A-Z]+)$'], $tool['inputSchema']['properties']['code']);
    }

    public function testARefusedBlankIsRefusedThoughTheRuleMatchesIt(): void
    {
        // NotBlank refuses '', which `^[A-Z]*$` matches: the pattern refuses it as the server does.
        $text = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType';
        $code = new CollectedFormField(name: 'code', formTypeClass: $text, required: false, mapped: true, compound: false, dataClass: null, constraints: [new NotBlank(), new Regex('/^[A-Z]*$/D')]);
        $raw = new CollectedFormField(name: 'raw', formTypeClass: $text, required: false, mapped: true, compound: false, dataClass: null, constraints: [new NotBlank(), new Regex('/^[A-Z]*$/')], trim: false);

        $properties = $this->toolWithBody('POST', [$code, $raw], $this->shape('array{code?: string, raw?: string}'))['inputSchema']['properties'];
        self::assertSame(['type' => 'string', 'pattern' => '^(?!$)(?:[A-Z]*)$'], $properties['code']);
        self::assertSame(['type' => 'string', 'pattern' => '^(?!$)(?:[A-Z]*)\\n?$'], $properties['raw']);
    }

    public function testABlankTheServerTakesIsTakenThoughALengthBoundsTheRest(): void
    {
        // '' is submitted null, which Length skips: a minimum holds the values that aren't blank.
        $text = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType';
        $code = new CollectedFormField(name: 'code', formTypeClass: $text, required: false, mapped: true, compound: false, dataClass: null, constraints: [new Length(min: 3), new Regex('/^[A-Z]+$/D')]);
        $named = new CollectedFormField(name: 'named', formTypeClass: $text, required: false, mapped: true, compound: false, dataClass: null, constraints: [new NotBlank(), new Length(min: 3)]);
        $short = new CollectedFormField(name: 'short', formTypeClass: $text, required: false, mapped: true, compound: false, dataClass: null, constraints: [new Length(min: 0, max: 5)]);

        $properties = $this->toolWithBody('PUT', [$code, $named, $short], $this->shape('array{code?: string, named?: string, short?: string}'))['inputSchema']['properties'];
        self::assertSame(['type' => 'string', 'anyOf' => [['maxLength' => 0], ['minLength' => 3]], 'pattern' => '^(?:[A-Z]+)?$'], $properties['code']);
        self::assertSame(['type' => 'string', 'minLength' => 3], $properties['named'], 'Its blank refused, a minimum plain');
        self::assertSame(['type' => 'string', 'minLength' => 0, 'maxLength' => 5], $properties['short'], 'A minimum of none refuses no blank');
    }

    public function testANullTheUnionHasIsAllowedOnce(): void
    {
        // `Status|null`, Status a union with null among its members: one null in the enum — and one
        // among the alternatives of a union of types.
        $spec = new CollectedFormField(name: 'spec', formTypeClass: 'App\\SpecType', required: false, mapped: true, compound: true, dataClass: 'App\\Spec', contract: new ShapeType([
            new ShapeField('status', new NullableType(new UnionType([new LiteralType('open'), new LiteralType('closed'), new ScalarType('null')]), optional: false), optional: false),
            new ShapeField('ref', new NullableType(new UnionType([new ScalarType('string'), new ScalarType('int'), new ScalarType('null')]), optional: false), optional: false),
        ]));

        $properties = $this->toolWithBody('PUT', [$spec], new ShapeType([]))['inputSchema']['properties']['spec']['properties'];
        self::assertSame(['enum' => ['open', 'closed', null]], $properties['status']);
        self::assertSame(['anyOf' => [['type' => 'string'], ['type' => 'integer'], ['type' => 'null']]], $properties['ref']);
    }

    public function testAnEscapedBackslashIsNoControlEscape(): void
    {
        // `\\\\n` is a backslash, then `n`: no padding a trimmed value could carry.
        $text = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType';
        $path = new CollectedFormField(name: 'path', formTypeClass: $text, required: false, mapped: true, compound: false, dataClass: null, constraints: [new NotBlank(), new Regex('/^\\\\n[A-Z]$/D')]);

        $properties = $this->toolWithBody('POST', [$path], $this->shape('array{path?: string}'))['inputSchema']['properties'];
        self::assertSame(['type' => 'string', 'pattern' => '^(?:\\\\n[A-Z])$'], $properties['path']);
    }

    public function testAWordBoundaryCannotMatchPaddingAClassBackspaceCan(): void
    {
        // Outside a class `\\b` is a word boundary, which no padding matches: a trimmed field keeps
        // its pattern. Inside one it is a backspace, which a padded value could carry.
        $text = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType';
        $word = new CollectedFormField(name: 'word', formTypeClass: $text, required: false, mapped: true, compound: false, dataClass: null, constraints: [new NotBlank(), new Regex('/^\\bfoo\\b$/D')]);
        $class = new CollectedFormField(name: 'class', formTypeClass: $text, required: false, mapped: true, compound: false, dataClass: null, constraints: [new NotBlank(), new Regex('/^[a\\b]+$/D')]);
        $after = new CollectedFormField(name: 'after', formTypeClass: $text, required: false, mapped: true, compound: false, dataClass: null, constraints: [new NotBlank(), new Regex('/^[A-Z]+\\b$/D')]);

        $properties = $this->toolWithBody('POST', [$word, $class, $after], $this->shape('array{word?: string, class?: string, after?: string}'))['inputSchema']['properties'];
        self::assertSame(['type' => 'string', 'pattern' => '^(?:\\bfoo\\b)$'], $properties['word']);
        self::assertSame(['type' => 'string'], $properties['class']);
        self::assertSame(['type' => 'string', 'pattern' => '^(?:[A-Z]+\\b)$'], $properties['after'], 'A boundary after a class closes');
    }

    public function testAConstraintNestedInAnotherGroupBoundsNothing(): void
    {
        // The sequence is in both groups, its Regex and Length in Strict alone: validated by
        // default, 'abcdef' is taken, so no client may be told to refuse it.
        $text = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType';
        $sequence = new Sequentially([new Regex('/^\\d+$/', groups: ['Strict']), new Length(max: 3, groups: ['Strict'])], groups: ['Default', 'Strict']);
        self::assertCount(0, Validation::createValidator()->validate('abcdef', [$sequence]));
        $code = new CollectedFormField(name: 'code', formTypeClass: $text, required: false, mapped: true, compound: false, dataClass: null, constraints: [$sequence]);

        $tool = $this->toolWithBody('POST', [$code], $this->shape('array{code?: string}'));
        self::assertSame(['type' => 'string'], $tool['inputSchema']['properties']['code']);
    }

    public function testAPathParamIsTheJsonTypeItsTypeIs(): void
    {
        // A number, a boolean, or what a path segment is: a string, whatever narrower type it has.
        $properties = $this->pathTool([
            new CollectedPathParam('id', 'number'),
            new CollectedPathParam('flag', 'boolean'),
            new CollectedPathParam('kind', "'a' | 'b'"),
            new CollectedPathParam('ref', 'Ref', standsFor: 'number'),
        ]);

        self::assertSame(['type' => 'number'], $properties['id']);
        self::assertSame(['type' => 'boolean'], $properties['flag']);
        self::assertSame(['type' => 'string'], $properties['kind']);
        self::assertSame(['type' => 'number'], $properties['ref']);
    }

    public function testAnEntrysBlankItsTransformerFailsIsRefusedToo(): void
    {
        $collection = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\CollectionType';
        $text = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType';
        $serials = new CollectedFormField(name: 'serials', formTypeClass: $collection, required: false, mapped: true, compound: true, dataClass: null, entryTypeClass: $text, entryBlankValue: new FailedBlank());

        $body = $this->toolWithBody('PUT', [$serials], $this->shape('array{serials?: list<string|null>}'))['inputSchema']['properties'];
        self::assertSame(['type' => 'string'], $body['serials']['items']);
    }

    public function testABlankItsTransformerFailsIsRefused(): void
    {
        // Submitted blank, the serial's transformer fails: the form is invalid, a 422. So a POST
        // requires it, and no update may send it null — whatever its contract says.
        $fields = array_column((new FormTypeInspector())->inspect(BlankNoteType::class)['fields'], null, 'name');
        $contract = $this->shape('array{serial?: string|null}');

        self::assertSame(['serial'], $this->toolWithBody('POST', [$fields['serial']], $contract)['inputSchema']['required'] ?? []);
        self::assertSame(['type' => 'string'], $this->toolWithBody('PUT', [$fields['serial']], $contract)['inputSchema']['properties']['serial']);
    }

    public function testAKeyAnIntersectionRequiresOnEitherSideIsRequired(): void
    {
        // `Base & array{product?: string}` where Base says `product: string`: both hold, so the key
        // can't be left out — as the Endpoint's `query:` says.
        $product = $this->scalarField('product', 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType', required: false);
        $endpoint = new CollectedEndpointContract(
            name: 'listTasks',
            domain: 'tasks',
            controllerClass: 'App\\ListController',
            methodName: '__invoke',
            responses: [],
            request: new CollectedEndpointRequest(
                query: new CollectedInputReference(null, 'App\\TaskFilterData', 'TaskFilterData', 'tasks', [$product], $this->shape('array{product?: string}'), $this->shape('array{product: string}')),
            ),
            mcp: new CollectedMcpTool(name: 'listTasks', description: 'The tasks.', httpMethod: 'GET', httpPath: '/tasks', destructive: false),
        );

        self::assertSame(['product'], (new McpManifestBuilder())->build(['tasks' => [$endpoint]])['tools'][0]['inputSchema']['required'] ?? []);
    }

    public function testAKeyAnIntersectionLetsTakeNullOnOneSideOnlyTakesNone(): void
    {
        // `Base & array{description?: string|null}` where Base says `description?: string`: the
        // intersection admits no null, as the TypeScript's `Base & {…}` doesn't.
        $text = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType';
        $description = $this->scalarField('description', $text, required: false);
        $endpoint = new CollectedEndpointContract(
            name: 'saveTask',
            domain: 'tasks',
            controllerClass: 'App\\TaskController',
            methodName: '__invoke',
            responses: [],
            request: new CollectedEndpointRequest(
                body: new CollectedInputReference(null, 'App\\TaskData', 'TaskData', 'tasks', [$description], $this->shape('array{description?: string|null}'), $this->shape('array{description?: string}')),
            ),
            mcp: new CollectedMcpTool(name: 'saveTask', description: 'Save a task.', httpMethod: 'PUT', httpPath: '/tasks', destructive: true),
        );

        $schema = (new McpManifestBuilder())->build(['tasks' => [$endpoint]])['tools'][0]['inputSchema'];
        self::assertSame(['type' => 'string'], $schema['properties']['description']);
        self::assertArrayNotHasKey('required', $schema);
    }

    public function testANestedKeyIsRequiredAsItsContractSays(): void
    {
        // An embedded form never sets `required`, which defaults to true; the contract says `line2`
        // may be left out — inside an object as at the top. A POST still requires what its form
        // refuses blank, nested or not.
        $text = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType';
        $address = new CollectedFormField(
            name: 'address',
            formTypeClass: 'App\\AddressType',
            required: true,
            mapped: true,
            compound: true,
            dataClass: 'App\\AddressData',
            children: [
                $this->scalarField('line1', $text, required: true),
                $this->scalarField('line2', $text, required: true),
                new CollectedFormField(name: 'postcode', formTypeClass: $text, required: true, mapped: true, compound: false, dataClass: null, constraints: [new NotBlank()]),
            ],
        );
        $contract = $this->shape('array{address: array{line1: string, line2?: string, postcode?: string}}');

        self::assertSame(['line1'], $this->toolWithBody('PUT', [$address], $contract)['inputSchema']['properties']['address']['required']);
        self::assertSame(['line1', 'postcode'], $this->toolWithBody('POST', [$address], $contract)['inputSchema']['properties']['address']['required']);
    }

    public function testAKeyTypedMixedMayBeLeftOutOfAQuery(): void
    {
        // `mixed` admits null, as its TypeScript `unknown` does: AsQuery<T> makes it optional.
        $filter = $this->scalarField('filter', 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType', required: true);

        self::assertArrayNotHasKey('required', $this->toolTaking([$filter], $this->shape('array{filter: mixed}'))['inputSchema']);
    }

    public function testAQueryKeyThatMayBeNullMayBeLeftOut(): void
    {
        // A query string carries no null: `product: string|null` is sent by leaving it out.
        $product = $this->scalarField('product', 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType', required: true);
        $contract = (new PhpDocShapeParser())->parse('array{product: string|null}');
        self::assertInstanceOf(ShapeType::class, $contract);

        $schema = $this->toolTaking([$product], $contract)['inputSchema'];
        self::assertArrayNotHasKey('required', $schema);
        self::assertSame(['type' => 'string'], $schema['properties']['product']);
    }

    public function testACollectionEntryItsConstraintsHoldToANonNullValueTakesNoNull(): void
    {
        $text = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType';
        $tags = new CollectedFormField(name: 'tags', formTypeClass: 'Symfony\\Component\\Form\\Extension\\Core\\Type\\CollectionType', required: false, mapped: true, compound: true, dataClass: null, entryTypeClass: $text, entryConstraints: [new NotNull()]);
        $labels = new CollectedFormField(name: 'labels', formTypeClass: 'Symfony\\Component\\Form\\Extension\\Core\\Type\\CollectionType', required: false, mapped: true, compound: true, dataClass: null, entryTypeClass: $text);
        $contract = (new PhpDocShapeParser())->parse('array{tags?: list<string|null>, labels?: list<string|null>}');
        self::assertInstanceOf(ShapeType::class, $contract);

        $body = $this->toolWithBody('PUT', [$tags, $labels], $contract)['inputSchema']['properties'];
        self::assertSame(['type' => 'string'], $body['tags']['items']);
        self::assertSame(['type' => ['string', 'null']], $body['labels']['items']);
    }

    public function testACollectionEntryKeptAnEmptyStringTakesNoNullWhereANotBlankRefusesIt(): void
    {
        // Its entry_options give each entry empty_data '': a null sent for one reaches validation
        // as '', which NotBlank(allowNull: true) refuses and a NotNull lets through.
        $text = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType';
        $collection = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\CollectionType';
        $codes = new CollectedFormField(name: 'codes', formTypeClass: $collection, required: false, mapped: true, compound: true, dataClass: null, entryTypeClass: $text, entryConstraints: [new NotBlank(allowNull: true)], entryBlankValue: '');
        $notes = new CollectedFormField(name: 'notes', formTypeClass: $collection, required: false, mapped: true, compound: true, dataClass: null, entryTypeClass: $text, entryConstraints: [new NotNull()], entryBlankValue: '');
        $contract = (new PhpDocShapeParser())->parse('array{codes?: list<string|null>, notes?: list<string|null>}');
        self::assertInstanceOf(ShapeType::class, $contract);

        $body = $this->toolWithBody('PUT', [$codes, $notes], $contract)['inputSchema']['properties'];
        self::assertSame(['type' => 'string'], $body['codes']['items']);
        self::assertSame(['type' => ['string', 'null']], $body['notes']['items']);
    }

    public function testARegexOnAValueTheFormTransformsIsNoPattern(): void
    {
        // It validates what the transformer makes of the value — `so-12` uppercased, say — not what
        // a caller sends, so as a pattern it would refuse what the server takes.
        $field = new CollectedFormField(name: 'code', formTypeClass: 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType', required: false, mapped: true, compound: false, dataClass: null, constraints: [new Regex('/^[A-Z]+-[0-9]+$/')], transformsValue: true);

        self::assertSame(['type' => 'string'], $this->toolWithBody('PUT', [$field], new ShapeType([]))['inputSchema']['properties']['code']);
    }

    public function testAFieldRebuiltWithItsDeclaredContractsKeepsItsTrim(): void
    {
        $field = new CollectedFormField(name: 'code', formTypeClass: 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType', required: false, mapped: true, compound: false, dataClass: null, trim: false);

        self::assertFalse($field->withDeclared([], [], null)->trim);
    }

    public function testAKeyInsideAnEmbeddedFormOrACollectionEntryTakesNullWhereItsContractSaysSo(): void
    {
        // The form says what an embedded form's and an entry's fields are; the contract still says
        // which of them an update may clear.
        $text = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType';
        $address = new CollectedFormField(
            name: 'address',
            formTypeClass: 'App\\AddressType',
            required: false,
            mapped: true,
            compound: true,
            dataClass: 'App\\AddressData',
            children: [$this->scalarField('line1', $text, required: true), $this->scalarField('line2', $text, required: false)],
        );
        $notes = $this->collectionField('notes', 'App\\NoteType', required: false, entryChildren: [$this->scalarField('note', $text, required: false)]);
        $tags = $this->collectionField('tags', $text, required: false);
        $contract = (new PhpDocShapeParser())->parse('array{address?: array{line1: string, line2: string|null}, notes?: list<array{note: string|null}>, tags?: list<string|null>}');
        self::assertInstanceOf(ShapeType::class, $contract);

        $body = $this->toolWithBody('PUT', [$address, $notes, $tags], $contract)['inputSchema']['properties'];

        self::assertSame(['type' => 'string'], $body['address']['properties']['line1']);
        self::assertSame(['type' => ['string', 'null']], $body['address']['properties']['line2']);
        self::assertSame(['type' => ['string', 'null']], $body['notes']['items']['properties']['note']);
        self::assertSame(['type' => ['string', 'null']], $body['tags']['items']);

        $query = $this->toolTaking([$address, $notes, $tags], $contract)['inputSchema']['properties'];
        self::assertSame(['type' => 'string'], $query['address']['properties']['line2']);
        self::assertSame(['type' => 'string'], $query['notes']['items']['properties']['note']);
        self::assertSame(['type' => 'string'], $query['tags']['items']);
    }

    public function testAnEmbeddedFormTakesNoNullWhateverItsContractSays(): void
    {
        // Symfony submits a compound form's children, never the form as a whole: a null sent for it
        // is an empty array, so its fields are cleared one by one — a 422 from a NotBlank, or a
        // record of nulls — and never the null an update's "clear this" means. A list's null is an
        // empty list, which is a clear.
        $text = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType';
        $address = new CollectedFormField(
            name: 'address',
            formTypeClass: 'App\\AddressType',
            required: false,
            mapped: true,
            compound: true,
            dataClass: 'App\\AddressData',
            children: [$this->scalarField('street', $text, required: true)],
        );
        $billing = new CollectedFormField(
            name: 'billing',
            formTypeClass: 'App\\BillingType',
            required: false,
            mapped: true,
            compound: true,
            dataClass: 'App\\BillingData',
            children: [$address],
        );
        $tags = $this->collectionField('tags', $text, required: false);
        $contract = (new PhpDocShapeParser())->parse('array{address?: array{street: string}|null, billing?: array{address: array{street: string}|null}, tags?: list<string>|null}');
        self::assertInstanceOf(ShapeType::class, $contract);

        $body = $this->toolWithBody('PUT', [$address, $billing, $tags], $contract)['inputSchema']['properties'];

        self::assertSame('object', $body['address']['type']);
        self::assertSame('object', $body['billing']['properties']['address']['type']);
        self::assertSame(['array', 'null'], $body['tags']['type']);
    }

    public function testAKeyThatMayBeNullInsideAQueryShapeMayBeLeftOut(): void
    {
        // A query string carries no null, nested or not: `from: string|null` is sent by leaving it out.
        $range = new CollectedFormField(
            name: 'range',
            formTypeClass: 'App\\RangeType',
            required: false,
            mapped: true,
            compound: true,
            dataClass: 'App\\RangeData',
            contract: $this->shape('array{from: string|null, to?: string, until: string}'),
        );
        $contract = $this->shape('array{range?: array{from: string|null, to?: string, until: string}}');

        $query = $this->toolTaking([$range], $contract)['inputSchema']['properties']['range'];
        self::assertSame(['until'], $query['required']);
        self::assertSame(['type' => 'string'], $query['properties']['from']);

        $body = $this->toolWithBody('PUT', [$range], $contract)['inputSchema']['properties']['range'];
        self::assertSame(['from', 'until'], $body['required']);
        self::assertSame(['type' => ['string', 'null']], $body['properties']['from']);
    }

    private function shape(string $type): ShapeType
    {
        $shape = (new PhpDocShapeParser())->parse($type);
        self::assertInstanceOf(ShapeType::class, $shape);

        return $shape;
    }

    /**
     * @param list<CollectedPathParam> $params
     *
     * @return array<string, mixed> the tool's input properties
     */
    private function pathTool(array $params): array
    {
        $endpoint = new CollectedEndpointContract(
            name: 'showThing',
            domain: 'things',
            controllerClass: 'App\\ThingController',
            methodName: '__invoke',
            responses: [],
            request: new CollectedEndpointRequest(pathParams: $params),
            mcp: new CollectedMcpTool(name: 'showThing', description: 'A thing.', httpMethod: 'GET', httpPath: '/things/' . implode('/', array_map(static fn (CollectedPathParam $param): string => '{' . $param->name . '}', $params)), destructive: false),
        );

        return (new McpManifestBuilder())->build(['things' => [$endpoint]])['tools'][0]['inputSchema']['properties'];
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
