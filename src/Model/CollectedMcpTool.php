<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

/**
 * The resolved MCP-tool facet of an endpoint contract: present only when the controller method
 * carries #[McpTool]. Name/description/destructive are the resolved values (attribute overrides
 * applied, method-derived defaults filled in); httpMethod comes from #[Route], httpPath from the
 * router when the collector has one (the served path, prefixes included) and from #[Route]
 * otherwise; scopes
 * are the auth-scope values read from the project's scope attribute (empty when the project
 * does not configure one).
 */
final readonly class CollectedMcpTool
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public string $httpMethod,
        public string $httpPath,
        public bool $destructive,
        public array $scopes = [],
    ) {}
}
