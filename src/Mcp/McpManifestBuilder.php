<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Mcp;

use PTGS\TypeBridge\Config\IncludeConvention;
use PTGS\TypeBridge\Model\CollectedEndpointContract;
use PTGS\TypeBridge\Model\CollectedFormField;
use PTGS\TypeBridge\Model\CollectedInputReference;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\ParsedType;
use PTGS\TypeBridge\Parser\ScalarType;
use PTGS\TypeBridge\Parser\ShapeField;

/**
 * Builds the MCP tool manifest (the `tools.json` structure) from collected endpoint contracts.
 * Only contracts carrying #[McpTool] (i.e. `->mcp !== null`) become tools.
 *
 * Each tool's `inputSchema` is a JSON Schema object assembled from the endpoint's path params,
 * query and body fields — the arguments an MCP client supplies. Whether an argument is required,
 * and which scalar it is, come from the request contract the input class declares (`_self`), the
 * same shape the generated TypeScript is typed from, so a tool and a typed client never disagree
 * about one endpoint; the form speaks only for what the contract does not say. The HTTP method + path tell the
 * runtime how to call the API, and `query` (when there are any) names the arguments it sends in the
 * query string whatever the method — a POST can take query parameters as well as a body; `destructive` is the safety hint; `scopes` (when the project
 * configures a scope attribute) names the auth scopes the calling token must hold, letting the
 * runtime filter or annotate tools the caller cannot use. (Response output schemas are a later
 * increment — MCP `outputSchema` is optional.)
 */
final class McpManifestBuilder
{
    /**
     * @param IncludeConvention $includes whose `query` parameters every tool with a response body takes
     */
    public function __construct(
        private readonly IncludeConvention $includes = new IncludeConvention(),
    ) {}

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
        $tool['description'] = $mcp->description;
        $tool['method'] = $mcp->httpMethod;
        $tool['path'] = $mcp->httpPath;
        $tool['destructive'] = $mcp->destructive;
        if ([] !== $mcp->scopes) {
            $tool['scopes'] = $mcp->scopes;
        }
        $tool['inputSchema'] = $this->inputSchema($contract);

        $query = array_map(static fn (CollectedFormField $field): string => $field->name, $contract->request?->query->fields ?? []);
        $query = array_values(array_unique([...$query, ...array_keys($this->includeQuery($contract))]));
        if ([] !== $query) {
            $tool['query'] = $query;
        }

        return $tool;
    }

    /**
     * The include query parameters this tool takes: all of them when its response has a body to
     * shape, none otherwise.
     *
     * @return array<string, string>
     */
    private function includeQuery(CollectedEndpointContract $contract): array
    {
        return $contract->hasSuccessBody() ? $this->includes->query : [];
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

        foreach ($this->includeQuery($contract) as $name => $description) {
            $properties[$name] ??= ['type' => 'string', 'description' => $description];
        }

        // An endpoint with no inputs is a bare `{type: object}`: an empty PHP array would
        // encode as a JSON array, and JSON Schema requires `properties` to be an object.
        $schema = ['type' => 'object'];
        if ([] !== $properties) {
            $schema['properties'] = $properties;
        }
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

        $declared = [];
        foreach ($reference->contract->fields ?? [] as $key) {
            $declared[$key->name] = $key;
        }

        foreach ($reference->fields as $field) {
            $key = $declared[$field->name] ?? null;
            $properties[$field->name] = $this->fieldSchema($field, $key?->type);
            if ($this->isRequired($field, $key)) {
                $required[] = $field->name;
            }
        }
    }

    /**
     * A key the contract declares is required exactly when the contract says so: `name: string`
     * is, `name?: string` is not.
     *
     * The form's own `required` option cannot answer this. It defaults to true and nothing
     * enforces it on a submitted request, so a filter form that never mentions it would publish
     * every filter as mandatory, and a model could not list anything without inventing a value
     * for each. It is the fallback for a field the contract does not declare.
     */
    private function isRequired(CollectedFormField $field, ?ShapeField $key): bool
    {
        return null === $key ? $field->required : !$key->optional;
    }

    /**
     * @param ParsedType|null $declared the type the request contract gives this field, if it names it
     *
     * @return array<string, mixed>
     */
    private function fieldSchema(CollectedFormField $field, ?ParsedType $declared = null): array
    {
        // A collection is compound too, but its shape is its entry's, repeated.
        if (null !== $field->entryTypeClass) {
            return ['type' => 'array', 'items' => $this->entrySchema($field)];
        }

        // An enum field takes one of a known set, and a model has no other way to learn them: the
        // values go in the schema, so a wrong one is refused by the client rather than by a 422 the
        // model has to guess its way out of. `multiple` means a list of them (a status filter).
        if (null !== $field->enumClass) {
            $leaf = $this->enumSchema($field->enumClass);

            return $field->multiple ? ['type' => 'array', 'items' => $leaf] : $leaf;
        }

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

        return ['type' => $this->declaredScalar($declared) ?? $this->scalarType($field)];
    }

    /**
     * The JSON Schema type of a scalar the contract declares. A form type's name is a guess at
     * this (a CheckboxType binds a boolean and says so nowhere in its name); the contract states it.
     */
    private function declaredScalar(?ParsedType $declared): ?string
    {
        if ($declared instanceof NullableType) {
            $declared = $declared->inner;
        }
        if (!$declared instanceof ScalarType) {
            return null;
        }

        return match ($declared->base()) {
            'string' => 'string',
            'int' => 'integer',
            'float' => 'number',
            'bool' => 'boolean',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function entrySchema(CollectedFormField $field): array
    {
        if ([] === $field->entryChildren) {
            \assert(null !== $field->entryTypeClass);

            return ['type' => $this->scalarTypeOf($field->entryTypeClass)];
        }

        $properties = [];
        $required = [];
        foreach ($field->entryChildren as $child) {
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

    /**
     * The values a backed enum allows, typed by what it is backed with.
     *
     * @return array{type: string, enum: list<int|string>}
     */
    private function enumSchema(string $enumClass): array
    {
        if (!is_a($enumClass, \BackedEnum::class, allow_string: true)) {
            throw new \RuntimeException(\sprintf('`%s` is used as a form field\'s enum but is not a backed enum.', $enumClass));
        }

        $values = array_map(static fn (\BackedEnum $case): int|string => $case->value, $enumClass::cases());

        return ['type' => [] !== $values && is_int($values[0]) ? 'integer' : 'string', 'enum' => $values];
    }

    private function scalarType(CollectedFormField $field): string
    {
        return $this->scalarTypeOf($field->formTypeClass);
    }

    private function scalarTypeOf(string $formTypeClass): string
    {
        $shortName = $this->shortName($formTypeClass);

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
