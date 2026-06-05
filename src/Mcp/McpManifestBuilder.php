<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Mcp;

use PTGS\TypeBridge\Model\CollectedEndpointContract;
use PTGS\TypeBridge\Model\CollectedFormField;
use PTGS\TypeBridge\Model\CollectedInputReference;

/**
 * Builds the MCP tool manifest (the `tools.json` structure) from collected endpoint contracts.
 * Only contracts carrying #[McpTool] (i.e. `->mcp !== null`) become tools.
 *
 * Each tool's `inputSchema` is a JSON Schema object assembled from the endpoint's path params,
 * query and body fields — the arguments an MCP client supplies. The HTTP method + path tell the
 * runtime how to call the API; `destructive` is the safety hint. (Response output schemas are a
 * later increment — MCP `outputSchema` is optional.)
 */
final class McpManifestBuilder
{
    /**
     * @param array<string, list<CollectedEndpointContract>> $contractsByDomain
     *
     * @return array{tools: list<array<string, mixed>>}
     */
    public function build(array $contractsByDomain): array
    {
        $tools = [];
        foreach ($contractsByDomain as $contracts) {
            foreach ($contracts as $contract) {
                if (null === $contract->mcp) {
                    continue;
                }

                $tools[] = $this->tool($contract);
            }
        }

        usort($tools, static function (array $left, array $right): int {
            $leftName = $left['name'];
            $rightName = $right['name'];

            return (\is_string($leftName) ? $leftName : '') <=> (\is_string($rightName) ? $rightName : '');
        });

        return ['tools' => $tools];
    }

    /**
     * @return array<string, mixed>
     */
    private function tool(CollectedEndpointContract $contract): array
    {
        $mcp = $contract->mcp;
        \assert(null !== $mcp);

        $tool = ['name' => $mcp->name];
        if (null !== $mcp->description) {
            $tool['description'] = $mcp->description;
        }
        $tool['method'] = $mcp->httpMethod;
        $tool['path'] = $mcp->httpPath;
        $tool['destructive'] = $mcp->destructive;
        $tool['inputSchema'] = $this->inputSchema($contract);

        return $tool;
    }

    /**
     * @return array<string, mixed>
     */
    private function inputSchema(CollectedEndpointContract $contract): array
    {
        $properties = [];
        $required = [];

        $request = $contract->request;
        if (null !== $request) {
            foreach ($request->pathParams ?? [] as $param) {
                // A path param's tsType ('string'/'number') doubles as its JSON Schema type, and
                // a path segment is always required.
                $properties[$param->name] = ['type' => $param->tsType];
                $required[] = $param->name;
            }

            $this->addFields($request->query, $properties, $required);
            $this->addFields($request->body, $properties, $required);
        }

        $schema = ['type' => 'object', 'properties' => $properties];
        if ([] !== $required) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * @param array<string, mixed> $properties
     * @param list<string>         $required
     */
    private function addFields(?CollectedInputReference $reference, array &$properties, array &$required): void
    {
        if (null === $reference) {
            return;
        }

        foreach ($reference->fields as $field) {
            $properties[$field->name] = $this->fieldSchema($field);
            if ($field->required) {
                $required[] = $field->name;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldSchema(CollectedFormField $field): array
    {
        if ($field->compound && [] !== $field->children) {
            $properties = [];
            $required = [];
            foreach ($field->children as $child) {
                $properties[$child->name] = $this->fieldSchema($child);
                if ($child->required) {
                    $required[] = $child->name;
                }
            }

            $schema = ['type' => 'object', 'properties' => $properties];
            if ([] !== $required) {
                $schema['required'] = $required;
            }

            return $schema;
        }

        return ['type' => $this->scalarType($field)];
    }

    private function scalarType(CollectedFormField $field): string
    {
        $shortName = $this->shortName($field->formTypeClass);

        return match (true) {
            str_contains($shortName, 'Boolean') => 'boolean',
            str_contains($shortName, 'Integer') => 'integer',
            str_contains($shortName, 'Number'), str_contains($shortName, 'Money') => 'number',
            default => 'string',
        };
    }

    private function shortName(string $className): string
    {
        $position = strrpos($className, '\\');

        return false === $position ? $className : substr($className, $position + 1);
    }
}
