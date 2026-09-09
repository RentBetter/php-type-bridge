<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Mcp;

use PHPUnit\Framework\TestCase;
use PTGS\TypeBridge\Config\TypeBridgeConfig;
use PTGS\TypeBridge\Mcp\McpManifestGenerator;
use PTGS\TypeBridge\Tests\Fixture\DescribedMcpFixtures\Common\Spec\Api;
use RuntimeException;

/**
 * The generator is what both `typebridge:mcp` and a container-compiled manifest go through, so
 * this is where the config's keys are proven to reach the tools: `routing`, because a tool's
 * path must be the one Symfony serves, which the method attribute alone does not give; and
 * `mcpDescriptionAttribute`, because a tool's description comes from the endpoint's own docs.
 */
final class McpManifestGeneratorTest extends TestCase
{
    private const string ROUTED = __DIR__ . '/../Fixture/RoutedMcpFixtures';
    private const string UNROUTED = __DIR__ . '/../Fixture/UnroutedMcpFixtures';
    private const string DESCRIBED = __DIR__ . '/../Fixture/DescribedMcpFixtures';

    public function testTheDescriptionAttributeReachesTheToolsThroughTheConfig(): void
    {
        $manifest = (new McpManifestGenerator())->generate(
            self::DESCRIBED,
            TypeBridgeConfig::fromArray(['mcpDescriptionAttribute' => Api::class]),
        );

        self::assertSame('List the pings. Newest first.', array_column($manifest['tools'], 'description', 'name')['ListPings']);
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
