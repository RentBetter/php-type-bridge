<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

/**
 * The resolved MCP-tool facet of an endpoint contract: present only when the controller method
 * carries #[McpTool]. Name/description/destructive are the resolved values (attribute overrides
 * applied, method-derived defaults filled in); httpMethod/httpPath come from #[Route].
 */
final readonly class CollectedMcpTool
{
    public function __construct(
        public string $name,
        public ?string $description,
        public string $httpMethod,
        public string $httpPath,
        public bool $destructive,
    ) {}
}
