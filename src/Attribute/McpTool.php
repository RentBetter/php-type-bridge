<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Attribute;

use Attribute;

/**
 * Opt-in marker that exposes the annotated controller method as a generated MCP tool. The method
 * must also carry #[ApiResponses]; the tool's input/output JSON Schemas are derived from the
 * endpoint's request and response contracts, and its HTTP method + path from #[Route].
 *
 * Only endpoints explicitly marked with #[McpTool] become tools — the contract surface and the
 * tool surface are intentionally decoupled, so adding an API endpoint never silently exposes it
 * to MCP clients.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class McpTool
{
    /**
     * @param string|null $name        Override the derived tool name (defaults to the endpoint name).
     * @param string|null $description LLM-facing tool description; falls back to the method's
     *                                 docblock summary when omitted.
     * @param bool|null   $destructive Override the method-derived safety hint (a non-GET method is
     *                                 destructive by default; GET is read-only).
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?bool $destructive = null,
    ) {}
}
