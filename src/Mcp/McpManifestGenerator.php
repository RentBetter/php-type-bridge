<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Mcp;

use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Config\TypeBridgeConfig;

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
        );
        $contracts = $collector->collect($sourceDir, (new ResponseClassCollector())->collectIndex($sourceDir));

        return (new McpManifestBuilder())->build($contracts);
    }
}
