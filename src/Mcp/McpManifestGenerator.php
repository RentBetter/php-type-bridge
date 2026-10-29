<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Mcp;

use PTGS\TypeBridge\Collector\EndpointContractCollector;
use PTGS\TypeBridge\Collector\ResponseClassCollector;
use PTGS\TypeBridge\Config\TypeBridgeConfig;
use PTGS\TypeBridge\Support\ClassFileTypes;

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
        // One reader of the source files for both collectors: the tree is scanned, and each file read
        // for what it declares and imports, once.
        $files = new ClassFileTypes();
        $collector = new EndpointContractCollector(
            requirementTypes: $config->requirementTypes,
            mcpScopeAttribute: $config->mcpScopeAttribute,
            mcpScopeProperty: $config->mcpScopeProperty,
            mcpDescriptionAttribute: $config->mcpDescriptionAttribute,
            mcpDescriptionProperty: $config->mcpDescriptionProperty,
            routePathResolver: $config->routePathResolver(),
            files: $files,
            typeAliases: $config->typeAliases,
            routingEnv: $config->routingEnv,
        );
        // A tool reads of a response only what it answers with, never what it sends.
        $contracts = $collector->collect($sourceDir, (new ResponseClassCollector(files: $files, typeAliases: $config->typeAliases))->statusIndex($sourceDir));

        return (new McpManifestBuilder($config->includes, $config->mcpParamDescriptions(), $config->typeAliases))->build($contracts);
    }
}
