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
use PTGS\TypeBridge\Support\AttributeText;
use ReflectionProperty;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThan;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\Range;

/**
 * Builds the MCP tool manifest (the `tools.json` structure) from collected endpoint contracts.
 * Only contracts carrying #[McpTool] (i.e. `->mcp !== null`) become tools.
 *
 * Each tool's `inputSchema` is a JSON Schema object assembled from the endpoint's path params,
 * query and body fields — the arguments an MCP client supplies. Whether an argument is required,
 * and which scalar it is, come from the request contract the input class declares (`_self`), the
 * same shape the generated TypeScript is typed from, so a tool and a typed client never disagree
 * about one endpoint; the form speaks only for what the contract does not say, and for the values a
 * choice field accepts, which only the built form knows. Each argument also carries what its
 * validation bounds it by (a number's range, a string's length, a list's size) and, when the
 * project configures a parameter-documentation attribute, the description on the property it
 * binds. The HTTP method + path tell the runtime how to call the API, and `query` (when there are
 * any) names the arguments it sends in the query string whatever the method — a POST can take
 * query parameters as well as a body; `destructive` is the safety hint; `scopes` (when the project
 * configures a scope attribute) names the auth scopes the calling token must hold, letting the
 * runtime filter or annotate tools the caller cannot use. (Response output schemas are a later
 * increment — MCP `outputSchema` is optional.)
 */
final class McpManifestBuilder
{
    /**
     * The bounds where the tighter of two is the larger value; for the rest it is the smaller.
     */
    private const array LOWER_BOUNDS = ['minimum', 'exclusiveMinimum', 'minLength', 'minItems'];

    /**
     * @param IncludeConvention  $includes          whose `query` parameters every tool with a response body takes
     * @param AttributeText|null $paramDescriptions the project's parameter-documentation attribute, read off
     *                                              the data-class property each argument binds
     */
    public function __construct(
        private readonly IncludeConvention $includes = new IncludeConvention(),
        private readonly ?AttributeText $paramDescriptions = null,
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
     * The field's schema, with what its validation bounds it by and what its property says it is.
     *
     * @param ParsedType|null $declared the type the request contract gives this field, if it names it
     *
     * @return array<string, mixed>
     */
    private function fieldSchema(CollectedFormField $field, ?ParsedType $declared = null): array
    {
        $schema = $this->valueSchema($field, $declared);
        $schema = [...$schema, ...$this->bounds($field->constraints, $schema['type'])];
        if (null !== $description = $this->description($field)) {
            $schema['description'] = $description;
        }

        return $schema;
    }

    /**
     * @param ParsedType|null $declared the type the request contract gives this field, if it names it
     *
     * @return array{type: string, ...<string, mixed>}
     */
    private function valueSchema(CollectedFormField $field, ?ParsedType $declared): array
    {
        // A collection is compound too, but its shape is its entry's, repeated.
        if (null !== $field->entryTypeClass) {
            return ['type' => 'array', 'items' => $this->entrySchema($field)];
        }

        // A choice field takes one of a known set, and a model has no other way to learn them: the
        // values go in the schema, so a wrong one is refused by the client rather than by a 422 the
        // model has to guess its way out of. `multiple` means a list of them (a status filter).
        //
        // They are the form's own values, what it reads a submitted one as, so they are strings
        // whatever the contract declares the key as: an int-backed enum's form reads 1 as "1".
        if (null !== $field->choiceValues && [] !== $field->choiceValues) {
            $leaf = ['type' => 'string', 'enum' => $field->choiceValues];

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
     * What the field's constraints bound a value by, as the JSON Schema keywords for its type: a
     * number's range, a string's length, a list's size. Where two bound the same side, the tighter
     * one is what a request is held to.
     *
     * A bound the schema cannot state truthfully is left out rather than approximated: one that
     * compares with another property, a length counted in anything but characters or taken after
     * a normaliser, a limit that is not a number (a date).
     *
     * @param list<Constraint> $constraints
     *
     * @return array<string, int|float>
     */
    private function bounds(array $constraints, string $type): array
    {
        $bounds = [];
        foreach ($constraints as $constraint) {
            foreach ($this->boundsOf($constraint, $type) as $keyword => $limit) {
                if (!\is_int($limit) && !\is_float($limit)) {
                    continue;
                }

                $bounds[$keyword] = isset($bounds[$keyword]) ? $this->tighter($keyword, $bounds[$keyword], $limit) : $limit;
            }
        }

        return $bounds;
    }

    private function tighter(string $keyword, int|float $current, int|float $limit): int|float
    {
        return \in_array($keyword, self::LOWER_BOUNDS, true) ? max($current, $limit) : min($current, $limit);
    }

    /**
     * @return array<string, mixed> keyword => limit, not yet known to be a number
     */
    private function boundsOf(Constraint $constraint, string $type): array
    {
        $numeric = 'integer' === $type || 'number' === $type;

        return match (true) {
            $numeric && $constraint instanceof Range => [
                'minimum' => null === $constraint->minPropertyPath ? $constraint->min : null,
                'maximum' => null === $constraint->maxPropertyPath ? $constraint->max : null,
            ],
            // Positive, PositiveOrZero, Negative and NegativeOrZero are these four against zero.
            $numeric && $constraint instanceof GreaterThanOrEqual && null === $constraint->propertyPath => ['minimum' => $constraint->value],
            $numeric && $constraint instanceof GreaterThan && null === $constraint->propertyPath => ['exclusiveMinimum' => $constraint->value],
            $numeric && $constraint instanceof LessThanOrEqual && null === $constraint->propertyPath => ['maximum' => $constraint->value],
            $numeric && $constraint instanceof LessThan && null === $constraint->propertyPath => ['exclusiveMaximum' => $constraint->value],
            'string' === $type && $constraint instanceof Length && null === $constraint->normalizer && Length::COUNT_CODEPOINTS === $constraint->countUnit => [
                'minLength' => $constraint->min,
                'maxLength' => $constraint->max,
            ],
            'array' === $type && $constraint instanceof Count => ['minItems' => $constraint->min, 'maxItems' => $constraint->max],
            'array' === $type && $constraint instanceof Choice && $constraint->multiple => ['minItems' => $constraint->min, 'maxItems' => $constraint->max],
            default => [],
        };
    }

    /**
     * The text the project's parameter-documentation attribute holds on the property the field
     * binds, when one is configured and the field binds a property.
     */
    private function description(CollectedFormField $field): ?string
    {
        if (null === $this->paramDescriptions || null === $field->ownerClass || !class_exists($field->ownerClass)) {
            return null;
        }

        return $this->paramDescriptions->on(new ReflectionProperty($field->ownerClass, $field->propertyPath ?? $field->name));
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
