<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

/**
 * The resolved MCP-tool facet of an endpoint contract: present only when the controller method
 * carries #[McpTool]. Name/description/destructive are the resolved values (attribute overrides
 * applied, method-derived defaults filled in — a description always resolves, from the attribute,
 * the project's documentation attribute or the docblock); httpMethod comes from #[Route], httpPath
 * from the router when the collector has one (the served path, prefixes included) and from
 * #[Route] otherwise; scopes are the auth-scope values read from the project's scope attribute
 * (empty when the project does not configure one).
 */
final readonly class CollectedMcpTool
{
    /**
     * @param list<string> $scopes
     * @param string|null  $unserved why the application, as it is deployed, serves no route for the
     *        tool: the manifest fails on it, which the TypeScript, describing the endpoint as its
     *        route does, does not
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $httpMethod,
        public string $httpPath,
        public bool $destructive,
        public array $scopes = [],
        public ?string $unserved = null,
    ) {}
}
