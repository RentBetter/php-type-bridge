<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Mcp;

use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Config\TypeBridgeConfig;
use PTGS\TypeBridge\Routing\RoutePathResolver;

/**
 * The MCP tool manifest for a source tree: every #[McpTool] endpoint contract under it,
 * rendered as a tool.
 *
 * One entry point for the two places a manifest is wanted — the `typebridge:mcp` command
 * writing it to a file, and an app compiling it straight into its container so there is no
 * file to regenerate or commit. Both must see the same tools, so both go through here.
 */
final class McpManifestGenerator
{
    /**
     * @return array{tools: list<array<string, mixed>>}
     */
    public function generate(string $sourceDir, TypeBridgeConfig $config): array
    {
        $collector = new EndpointContractCollector(
            requirementTypes: $config->requirementTypes,
            mcpScopeAttribute: $config->mcpScopeAttribute,
            mcpScopeProperty: $config->mcpScopeProperty,
            mcpDescriptionAttribute: $config->mcpDescriptionAttribute,
            mcpDescriptionProperty: $config->mcpDescriptionProperty,
            routePathResolver: $this->routePathResolver($config),
        );
        $contracts = $collector->collect($sourceDir, (new ResponseClassCollector())->collectIndex($sourceDir));

        return (new McpManifestBuilder())->build($contracts);
    }

    /**
     * A resolver only when the config names a routing entrypoint — a resolver with no file
     * answers null for every method, which the collector would (rightly) refuse as unrouted.
     */
    private function routePathResolver(TypeBridgeConfig $config): ?RoutePathResolver
    {
        if (null === $config->routing || null === $config->projectDir) {
            return null;
        }

        return new RoutePathResolver($config->projectDir, $config->routing);
    }
}
