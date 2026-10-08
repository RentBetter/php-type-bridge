<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Mcp;

use PTGS\TypeBridge\Config\IncludeConvention;
use PTGS\TypeBridge\Model\CollectedEndpointContract;
use PTGS\TypeBridge\Model\CollectedFormField;
use PTGS\TypeBridge\Model\CollectedInputReference;
use PTGS\TypeBridge\Parser\IdOfType;
use PTGS\TypeBridge\Parser\ListType;
use PTGS\TypeBridge\Parser\LiteralType;
use PTGS\TypeBridge\Parser\MapType;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\ParsedType;
use PTGS\TypeBridge\Parser\ScalarType;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Parser\UnionType;
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
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
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
 * choice field accepts, which only the built form knows. One exception: the body of a POST — a
 * request that makes something — requires a field its constraints refuse blank (NotBlank, NotNull),
 * since a contract the request shares with an update declares every key optional, and a model would
 * otherwise learn what it can't leave out only from a 422. A key the contract declares as a scalar or
 * a shape (a record's id, or the fields to make one) is published as either (`anyOf`), though its form
 * shows only the shape. A compound field with no children, whose type reads the value sent whole, is
 * published as the shape its data class declares, since its form shows nothing. Each argument also carries what its
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
        $tool['inputSchema'] = $this->inputSchema($contract, $mcp->httpMethod);

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
    private function inputSchema(CollectedEndpointContract $contract, string $httpMethod): array
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
            $this->addFields($request->body, $properties, $required, makes: 'POST' === strtoupper($httpMethod));
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
     * @param bool                 $makes      whether the request makes something (a POST body), so a
     *                                         field its constraints refuse blank is one it can't go without
     */
    private function addFields(?CollectedInputReference $reference, array &$properties, array &$required, bool $makes = false): void
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
            if ($this->isRequired($field, $key) || ($makes && $this->refusesBlank($field))) {
                $required[] = $field->name;
            }
        }
    }

    /**
     * Whether a submitted value is refused blank or missing: a NotBlank or a NotNull among the
     * constraints it is validated against.
     */
    private function refusesBlank(CollectedFormField $field): bool
    {
        foreach ($field->constraints as $constraint) {
            if ($constraint instanceof NotBlank || $constraint instanceof NotNull) {
                return true;
            }
        }

        return false;
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

        // A key that takes a record's id or the fields to make one (`string|array{name: string}`)
        // is a compound form with a scalar the form lifts out before submit; the form shows only the
        // object, the contract both. Published as either, or a model could never send the id.
        $alternatives = 'object' === $schema['type'] ? $this->declaredScalars($declared) : [];
        if ([] !== $alternatives) {
            $schema = ['anyOf' => [...array_map(static fn (string $type): array => ['type' => $type], $alternatives), $schema]];
        }

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

        // A compound field with no children reads what is sent whole, so its form says nothing of
        // what it takes: the shape its data class declares does, where every part of it can be said.
        if (null !== $field->contract && null !== $contract = $this->shapeSchema($field->contract)) {
            return $contract;
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
     * The JSON Schema types of the scalars in a union the contract declares (`string|array{…}` ->
     * `['string']`); none when it declares no union.
     *
     * @return list<string>
     */
    private function declaredScalars(?ParsedType $declared): array
    {
        if ($declared instanceof NullableType) {
            $declared = $declared->inner;
        }
        if (!$declared instanceof UnionType) {
            return [];
        }

        $types = [];
        foreach ($declared->types as $member) {
            $type = $this->declaredScalar($member);
            if (null !== $type) {
                $types[] = $type;
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * The JSON Schema type of a scalar the contract declares. A form type's name is a guess at
     * this (a CheckboxType binds a boolean and says so nowhere in its name); the contract states it.
     */
    /**
     * A declared shape as JSON Schema, or null when any part of it names a type stated elsewhere
     * (an alias, a generic, a class constant) that the builder cannot read here.
     *
     * @return array{type: 'object', properties?: array<string, array<string, mixed>>, required?: list<string>}|null
     */
    private function shapeSchema(ShapeType $shape): ?array
    {
        $properties = [];
        $required = [];
        foreach ($shape->fields as $key) {
            $schema = $this->typeSchema($key->type);
            if (null === $schema) {
                return null;
            }

            $properties[$key->name] = $schema;
            if (!$key->optional) {
                $required[] = $key->name;
            }
        }

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
     * @return array<string, mixed>|null
     */
    private function typeSchema(ParsedType $type): ?array
    {
        if ($type instanceof NullableType) {
            return $this->typeSchema($type->inner);
        }

        $scalar = $this->declaredScalar($type);
        if (null !== $scalar) {
            return ['type' => $scalar];
        }

        if ($type instanceof ListType || $type instanceof MapType) {
            $items = $this->typeSchema($type instanceof ListType ? $type->inner : $type->value);
            if (null === $items) {
                return null;
            }

            return $type instanceof ListType ? ['type' => 'array', 'items' => $items] : ['type' => 'object', 'additionalProperties' => $items];
        }

        return match (true) {
            $type instanceof ShapeType => $this->shapeSchema($type),
            $type instanceof UnionType => $this->unionSchema($type),
            $type instanceof LiteralType => ['enum' => [$type->value]],
            // An enum's ids are its own to say and can't be read statically; that it is a string can.
            $type instanceof IdOfType => ['type' => 'string'],
            default => null,
        };
    }

    /**
     * A union of literals is the values it allows; anything else is either of its members. A
     * `null` member is dropped: a key that may be null is one a request leaves out.
     *
     * @return array<string, mixed>|null
     */
    private function unionSchema(UnionType $union): ?array
    {
        $members = [];
        $values = [];
        foreach ($union->types as $member) {
            if ($member instanceof ScalarType && 'null' === $member->base()) {
                continue;
            }

            $schema = $this->typeSchema($member);
            if (null === $schema) {
                return null;
            }
            $members[] = $schema;
            if ($member instanceof LiteralType) {
                $values[] = $member->value;
            }
        }

        if ([] !== $members && \count($values) === \count($members)) {
            return ['enum' => array_values(array_unique($values, \SORT_REGULAR))];
        }

        return 1 === \count($members) ? $members[0] : ['anyOf' => $members];
    }

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
