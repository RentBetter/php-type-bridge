<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Mcp;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Config\TypeBridgeConfig;
use PTGS\TypeBridge\Mcp\McpManifestGenerator;
use PTGS\TypeBridge\Parser\NameRefType;
use PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Common\Spec\Param;
use PTGS\TypeBridge\Tests\Fixture\DescribedMcpFixtures\Common\Spec\Api;
use RuntimeException;

/**
 * The generator is what both `typebridge:mcp` and a container-compiled manifest go through, so
 * this is where the config's keys are proven to reach the tools: `routing`, because a tool's
 * path must be the one Symfony serves, which the method attribute alone does not give;
 * `mcpDescriptionAttribute`, because a tool's description comes from the endpoint's own docs; and
 * `mcpParamDescriptionAttribute`, because an argument's comes from the property it binds.
 */
final class McpManifestGeneratorTest extends TestCase
{
    private const string ROUTED = __DIR__ . '/../Fixture/RoutedMcpFixtures';
    private const string UNROUTED = __DIR__ . '/../Fixture/UnroutedMcpFixtures';
    private const string DESCRIBED = __DIR__ . '/../Fixture/DescribedMcpFixtures';
    private const string ARGUMENTS = __DIR__ . '/../Fixture/ArgumentMcpFixtures';

    public function testTheDescriptionAttributeReachesTheToolsThroughTheConfig(): void
    {
        $manifest = (new McpManifestGenerator())->generate(
            self::DESCRIBED,
            TypeBridgeConfig::fromArray(['mcpDescriptionAttribute' => Api::class]),
        );

        self::assertSame('List the pings. Newest first.', array_column($manifest['tools'], 'description', 'name')['ListPings']);
    }

    public function testTheParameterDescriptionAttributeReachesTheArgumentsThroughTheConfig(): void
    {
        $manifest = (new McpManifestGenerator())->generate(
            self::ARGUMENTS,
            TypeBridgeConfig::fromArray(['mcpParamDescriptionAttribute' => Param::class]),
        );

        $verdict = array_column($manifest['tools'], 'inputSchema', 'name')['RecordVerdict'];
        self::assertIsArray($verdict);
        self::assertSame('The verdict.', $verdict['properties']['status']['description']);
    }

    public function testPublishesTheServedPathWhenRoutingIsConfigured(): void
    {
        // The attribute says `/ping`; routes.yaml prefixes the namespace with `/api`.
        $manifest = (new McpManifestGenerator())->generate(
            self::ROUTED,
            TypeBridgeConfig::fromArray(['routing' => 'config/routes.yaml'], self::ROUTED),
        );

        self::assertSame(['Ping'], array_column($manifest['tools'], 'name'));
        self::assertSame(['/api/ping'], array_column($manifest['tools'], 'path'));
    }

    public function testWhetherARouteMatchesByCharacterIsWhatTheRouterServes(): void
    {
        // The method says `utf8: false`; the import that loads it says `utf8: true`, and the import
        // wins. Matched by character, `\w` is Unicode in PCRE and ASCII in JSON Schema: no pattern.
        $dir = __DIR__ . '/../Fixture/Utf8RoutedMcpFixtures';
        $manifest = (new McpManifestGenerator())->generate($dir, TypeBridgeConfig::fromArray(['routing' => 'config/routes.yaml'], $dir));

        $code = array_column($manifest['tools'], 'inputSchema', 'name')['ShowCode']['properties']['code'] ?? null;
        self::assertSame(['type' => 'string'], $code);
    }

    public function testWhatAPathParamMustLookLikeIsWhatTheRouterServes(): void
    {
        // The method says a code is lettered; the import that loads it says a number will do too,
        // and the import wins: the tool must not refuse `123`, which the router serves.
        $dir = __DIR__ . '/../Fixture/RequirementRoutedMcpFixtures';
        $manifest = (new McpManifestGenerator())->generate($dir, TypeBridgeConfig::fromArray(['routing' => 'config/routes.yaml'], $dir));

        $properties = array_column($manifest['tools'], 'inputSchema', 'name')['ShowCode']['properties'] ?? [];
        self::assertSame(['type' => 'string', 'pattern' => '^(?:[A-Z]{2,5}-[0-9]+|[0-9]+)$'], $properties['code'] ?? null);
        // And what it is: the import's `[0-9]+` is a number.
        self::assertSame(['type' => 'integer', 'minimum' => 0], $properties['page'] ?? null);
    }

    public function testAMethodRoutedTwiceIsDescribedByItsFirstRoute(): void
    {
        // Its params are named from the first #[Route]; its path and their requirements must be
        // that route's too, not whichever route the collection happens to list last.
        $dir = __DIR__ . '/../Fixture/RequirementRoutedMcpFixtures';
        $tool = array_column((new McpManifestGenerator())->generate($dir, TypeBridgeConfig::fromArray(['routing' => 'config/routes.yaml'], $dir))['tools'], null, 'name')['ShowNote'] ?? [];

        self::assertSame('/notes/{id}', $tool['path'] ?? null);
        self::assertSame(['type' => 'integer', 'minimum' => 0], $tool['inputSchema']['properties']['id'] ?? null);
    }

    public function testItIsDescribedByItsFirstRouteWhateverTheRouterSortsFirst(): void
    {
        // A priority lists the second route first, and its path ends the way the first's does.
        $dir = __DIR__ . '/../Fixture/TwiceRoutedApp';
        $tools = array_column((new McpManifestGenerator())->generate($dir, TypeBridgeConfig::fromArray(['routing' => 'config/routes.yaml'], $dir))['tools'], null, 'name');

        self::assertSame('/api/notes/{id}', $tools['ShowNote']['path'] ?? null);
        self::assertSame(['type' => 'integer', 'minimum' => 0], $tools['ShowNote']['inputSchema']['properties']['id'] ?? null);
    }

    public function testARequirementOrADefaultWrittenInlineIsReadAsTheRouterReadsIt(): void
    {
        // `{page<[0-9]+>}` is the param `page`, a number; `{slug?intro}` is `slug`. Routed or not,
        // the path a client is given names them as the router serves them.
        $dir = __DIR__ . '/../Fixture/TwiceRoutedApp';
        foreach (['/api/pages/{page}/{slug}' => TypeBridgeConfig::fromArray(['routing' => 'config/routes.yaml'], $dir), '/pages/{page}/{slug}' => new TypeBridgeConfig()] as $path => $config) {
            $page = array_column((new McpManifestGenerator())->generate($dir, $config)['tools'], null, 'name')['ShowPage'] ?? [];

            self::assertSame($path, $page['path'] ?? null);
            self::assertSame(['type' => 'integer', 'minimum' => 0], $page['inputSchema']['properties']['page'] ?? null);
            self::assertSame(['type' => 'string'], $page['inputSchema']['properties']['slug'] ?? null);
            self::assertSame(['page', 'slug'], $page['inputSchema']['required'] ?? null);
        }
    }

    public function testAParamMappedToAnArgumentIsReadAsTheRouterReadsIt(): void
    {
        // `{slug:note}` is served as `{slug}`, routed or not: a client substituting `slug` finds it.
        $dir = __DIR__ . '/../Fixture/TwiceRoutedApp';
        foreach (['/api/notes/by-slug/{slug}' => TypeBridgeConfig::fromArray(['routing' => 'config/routes.yaml'], $dir), '/notes/by-slug/{slug}' => new TypeBridgeConfig()] as $path => $config) {
            $note = array_column((new McpManifestGenerator())->generate($dir, $config)['tools'], null, 'name')['ShowNoteBySlug'] ?? [];

            self::assertSame($path, $note['path'] ?? null);
            self::assertSame(['slug'], $note['inputSchema']['required'] ?? null);
        }
    }

    public function testARequirementWrittenAnchoredIsTypedAsTheProjectKeysIt(): void
    {
        // The project keys its requirement type `^\d+$`, as the attribute writes it; the router takes
        // the anchors off. Routed or not, the type is the project's.
        $dir = __DIR__ . '/../Fixture/TwiceRoutedApp';
        foreach ([TypeBridgeConfig::fromArray(['routing' => 'config/routes.yaml', 'requirementTypes' => ['^\\d+$' => 'number']], $dir), TypeBridgeConfig::fromArray(['requirementTypes' => ['^\\d+$' => 'number']])] as $config) {
            $anchored = array_column((new McpManifestGenerator())->generate($dir, $config)['tools'], null, 'name')['ShowAnchored'] ?? [];

            self::assertSame(['type' => 'integer', 'minimum' => 0], $anchored['inputSchema']['properties']['id'] ?? null);
            $code = array_column((new McpManifestGenerator())->generate($dir, $config)['tools'], null, 'name')['ShowAnchoredCode'] ?? [];
            self::assertSame(['type' => 'string', 'pattern' => '^(?:[a-z]+)$'], $code['inputSchema']['properties']['code'] ?? null);
        }
    }

    public function testAToolIsPublishedWhateverAResponseSends(): void
    {
        // The manifest asks of a response only its status: one whose properties can't be read —
        // typed with an alias from nowhere — fails its TypeScript, not the tools.
        $dir = __DIR__ . '/../Fixture/StatusOnlyFixtures';
        $tools = array_column((new McpManifestGenerator())->generate($dir, new TypeBridgeConfig())['tools'], null, 'name');

        self::assertSame('/api/notes/{id}', $tools['ShowNote']['path'] ?? null);
    }

    public function testARequirementTypedWithAProjectWideAliasIsTheJsonTypeItStandsFor(): void
    {
        // `Code` is a string, so the argument is one, holding to its requirement; `Id` is `Num`, a
        // number. A TypeScript alias is no JSON Schema type.
        $dir = __DIR__ . '/../Fixture/TwiceRoutedApp';
        $config = TypeBridgeConfig::fromArray(['requirementTypes' => ['^\\d+$' => 'Id', '[a-z]+' => 'Code'], 'typeAliases' => ['Id' => 'Num', 'Num' => 'number', 'Code' => 'string']]);
        $tools = array_column((new McpManifestGenerator())->generate($dir, $config)['tools'], null, 'name');

        self::assertSame(['type' => 'integer', 'minimum' => 0], $tools['ShowAnchored']['inputSchema']['properties']['id'] ?? null);
        self::assertSame(['type' => 'string', 'pattern' => '^(?:[a-z]+)$'], $tools['ShowAnchoredCode']['inputSchema']['properties']['code'] ?? null);
    }

    public function testAMethodWhoseFirstRouteIsNotServedIsDescribedByTheOneThatIs(): void
    {
        // Its first #[Route] is confined to the dev env; the router serves the second, so the path,
        // the params' names and their requirements are all that one's.
        $dir = __DIR__ . '/../Fixture/TwiceRoutedApp';
        $tool = array_column((new McpManifestGenerator())->generate($dir, TypeBridgeConfig::fromArray(['routing' => 'config/routes.yaml'], $dir))['tools'], null, 'name')['ShowEnvNote'] ?? [];

        self::assertSame('/api/env/notes/{id}', $tool['path'] ?? null);
        self::assertSame(['id'], $tool['inputSchema']['required'] ?? null);
        self::assertSame(['type' => 'string', 'pattern' => '^(?:[A-Z]+)$'], $tool['inputSchema']['properties']['id'] ?? null);
    }

    public function testWithoutRoutingTheAttributePathStands(): void
    {
        // Existing consumers configure no routing and put the full path on the attribute;
        // for them nothing changes.
        $manifest = (new McpManifestGenerator())->generate(self::ROUTED, new TypeBridgeConfig());

        self::assertSame(['/ping'], array_column($manifest['tools'], 'path'));
    }

    public function testFailsWhenTheRouterServesNoRouteForATool(): void
    {
        // Falling back to the attribute path here would publish a tool that 404s. The
        // routing loads fine — it just never imports the controller — so this is the
        // collector's own check, not a load error.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('serves no route for it');

        (new McpManifestGenerator())->generate(
            self::UNROUTED,
            TypeBridgeConfig::fromArray(['routing' => 'config/routes.yaml'], self::UNROUTED),
        );
    }

    public function testFailsForAToolNoRouteDeclaresWithNoRoutingToFindOneIn(): void
    {
        // Its route, if it has one, is written where the attributes don't say — YAML, say — and with
        // no routing configured there is nowhere to read it: published, it would be a GET of ''.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('NoteController::listNotesAction" is exposed as an MCP tool but no #[Route] declares it');

        (new McpManifestGenerator())->generate(__DIR__ . '/../Fixture/NoRouteToolFixtures', new TypeBridgeConfig());
    }

    public function testAFieldLeftOutIsRequiredWhereASubmitOfNothingRefusesIt(): void
    {
        // What a real submit of nothing refuses. With clearMissing off a field left out isn't
        // submitted: it keeps what a new OrderData holds there — null for `enabled` and `note`, which
        // their NotNull refuses, `standard` for `label` — and `memo`, bound to no property, is never
        // checked. With it on each is submitted blank: `qty` 0, which Positive refuses, `label` null
        // and `memo` blank, while an unticked `enabled` and a `note` of '' pass. A new filter's
        // `product` is null either way, which its NotBlank refuses.
        $required = static function (bool $clearMissing): array {
            $tools = (new McpManifestGenerator())->generate(__DIR__ . '/../Fixture/LeftOutFixtures', TypeBridgeConfig::fromArray(['clearMissing' => $clearMissing]))['tools'];

            return array_column(array_map(static fn (array $tool): array => [$tool['name'], $tool['inputSchema']['required'] ?? []], $tools), 1, 0);
        };

        self::assertSame(['CreateOrder' => ['enabled', 'note'], 'ListOrders' => ['product']], $required(false));
        self::assertSame(['CreateOrder' => ['qty', 'label', 'memo'], 'ListOrders' => ['product']], $required(true));
    }

    public function testAToolWhosePathIsAClassTakesItsRoutesPlaceholders(): void
    {
        // The class types the Endpoint's path; the tool still takes each placeholder, held to its
        // requirement as the router holds it.
        $tool = (new McpManifestGenerator())->generate(__DIR__ . '/../Fixture/PathClassToolFixtures', new TypeBridgeConfig())['tools'][0];

        self::assertSame(['id' => ['type' => 'integer', 'minimum' => 0]], $tool['inputSchema']['properties'] ?? null);
        self::assertSame(['id'], $tool['inputSchema']['required'] ?? null);
    }

    public function testAPathClassIsItsContractAsWritten(): void
    {
        // Nothing reads a path class's keys — its TypeScript names it, and the tool takes the route's
        // placeholders — so it isn't read through.
        $src = __DIR__ . '/../Fixture/PathClassToolFixtures';
        $contract = (new EndpointContractCollector())->collect($src, (new ResponseClassCollector())->statusIndex($src))['Notes'][0];

        self::assertInstanceOf(NameRefType::class, $contract->request?->path?->keys()?->fields[0]->type);
    }

    public function testFailsWhenTheRoutingFileCannotBeLoaded(): void
    {
        // RoutePathResolver degrades quietly for the PHPStan rules, where a missing file should
        // weaken a check rather than fail the run. A manifest has no such option: every tool
        // would silently carry an unserved path.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot resolve served route paths for MCP tools');

        (new McpManifestGenerator())->generate(
            self::ROUTED,
            TypeBridgeConfig::fromArray(['routing' => 'config/missing.yaml'], self::ROUTED),
        );
    }
}
