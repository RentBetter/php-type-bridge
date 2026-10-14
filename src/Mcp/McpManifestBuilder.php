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
use Symfony\Component\Validator\Constraints\Regex;

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
 * otherwise learn what it can't leave out only from a 422. A body key the contract types `T|null` takes
 * null — an update's "clear this" — where `?T` is only ever left out. A key the contract declares as a scalar or
 * a shape (a record's id, or the fields to make one) is published as either (`anyOf`), though its form
 * shows only the shape. A compound field with no children, whose type reads the value sent whole, is
 * published as the shape its data class declares, since its form shows nothing. Each argument also carries what its
 * validation bounds it by (a number's range, a string's length, a list's size) and, when the
 * project configures a parameter-documentation attribute, the description on the property it
 * binds, and a string what it must look like (`pattern`) — from its route requirement or its Regex
 * constraint, when that reads the same in JSON Schema's regex dialect. The HTTP method + path tell the runtime how to call the API, and `query` (when there are
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
                // a path segment is always required. A string's route requirement says what it
                // must look like, so a model learns a ticket is `SO-12` before the router's 404.
                $properties[$param->name] = ['type' => $param->tsType];
                $pattern = 'string' === $param->tsType && null !== $param->requirement ? self::routePattern($param->requirement) : null;
                if (null !== $pattern) {
                    $properties[$param->name]['pattern'] = $pattern;
                }
                $required[] = $param->name;
            }

            $this->addFields($request->query, $properties, $required);
            $this->addFields($request->body, $properties, $required, makes: 'POST' === strtoupper($httpMethod), json: true);
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
     * @param bool                 $json       whether the fields are sent as JSON (a body), which can carry a
     *                                         null; a query string cannot
     */
    private function addFields(?CollectedInputReference $reference, array &$properties, array &$required, bool $makes = false, bool $json = false): void
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
            $properties[$field->name] = $this->fieldSchema($field, $key?->type, $json);
            if ($json && $this->takesNull($key, $field)) {
                $properties[$field->name] = self::withNull($properties[$field->name]);
            }
            if ($this->isRequired($field, $key) || ($makes && $this->refusesBlank($field))) {
                $required[] = $field->name;
            }
        }
    }

    /**
     * Whether a submitted value is refused blank or missing: a NotNull, or a NotBlank that does not
     * allow null — a field left out is null, which `NotBlank(allowNull: true)` lets through.
     */
    private function refusesBlank(CollectedFormField $field): bool
    {
        foreach ($field->constraints as $constraint) {
            if ($constraint instanceof NotNull || ($constraint instanceof NotBlank && !$constraint->allowNull)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the key takes null as a value: the contract admits a null that is sent and means
     * something — an update's "clear this" — and its validation does not refuse it.
     */
    private function takesNull(?ShapeField $key, CollectedFormField $field): bool
    {
        if (null === $key || !self::admitsNull($key->type)) {
            return false;
        }

        // A contract one create and one update share says `string|null`; a NotNull, or a NotBlank
        // that does not allow null, still refuses it with a 422.
        return !$this->refusesBlank($field);
    }

    /**
     * Whether a type admits null as a value sent: `T|null`, or a union with a null among several
     * members (`'open'|'closed'|null`, which parses as a union, not a nullable). `?T` does not: it
     * is a key left out when null, and a project's preserveNull rule holds every nullable key to
     * `?T` but the ones whose null means something.
     */
    private static function admitsNull(ParsedType $type): bool
    {
        if ($type instanceof NullableType) {
            return !$type->optional;
        }
        if (!$type instanceof UnionType) {
            return false;
        }
        foreach ($type->types as $member) {
            if ($member instanceof ScalarType && 'null' === $member->base()) {
                return true;
            }
        }

        return false;
    }


    /**
     * The schema with null among what it accepts: a `null` type beside its own (in `anyOf`, or as
     * a type list), and in its `enum` when it lists its values, which would refuse null otherwise.
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private static function withNull(array $schema): array
    {
        if (isset($schema['anyOf']) && \is_array($schema['anyOf'])) {
            $schema['anyOf'][] = ['type' => 'null'];

            return $schema;
        }

        if (isset($schema['type']) && \is_string($schema['type'])) {
            $schema['type'] = [$schema['type'], 'null'];
        }
        if (isset($schema['enum']) && \is_array($schema['enum'])) {
            $schema['enum'][] = null;
        }

        return $schema;
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
        return null === $key ? $field->required : !$key->mayBeLeftOut();
    }

    /**
     * The field's schema, with what its validation bounds it by and what its property says it is.
     *
     * @param ParsedType|null $declared the type the request contract gives this field, if it names it
     * @param bool            $json     whether it is sent as JSON, which can carry a null
     *
     * @return array<string, mixed>
     */
    private function fieldSchema(CollectedFormField $field, ?ParsedType $declared = null, bool $json = false): array
    {
        $schema = $this->valueSchema($field, $declared, $json);
        $schema = [...$schema, ...$this->bounds($field->constraints, $schema['type']), ...$this->patternOf($field->constraints, $schema['type'])];

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
    private function valueSchema(CollectedFormField $field, ?ParsedType $declared, bool $json = false): array
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
        if (null !== $field->contract && null !== $contract = $this->shapeSchema($field->contract, $json)) {
            return $contract;
        }

        if ($field->compound && [] !== $field->children) {
            $properties = [];
            $required = [];
            foreach ($field->children as $child) {
                $properties[$child->name] = $this->fieldSchema($child, json: $json);
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
    private function shapeSchema(ShapeType $shape, bool $json = false): ?array
    {
        $properties = [];
        $required = [];
        foreach ($shape->fields as $key) {
            $schema = $this->typeSchema($key->type, $json);
            if (null === $schema) {
                return null;
            }

            $properties[$key->name] = $schema;
            if (!$key->mayBeLeftOut()) {
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
     * A declared type as JSON Schema. Sent as JSON, a `T|null` takes null beside its type — a key
     * inside a shape is cleared as much as one at the top — where `?T` is a key only left out; in a
     * query string, which cannot carry a null, neither does.
     *
     * @return array<string, mixed>|null
     */
    private function typeSchema(ParsedType $type, bool $json = false): ?array
    {
        if ($type instanceof NullableType) {
            $inner = $this->typeSchema($type->inner, $json);

            return null !== $inner && $json && !$type->optional ? self::withNull($inner) : $inner;
        }

        $scalar = $this->declaredScalar($type);
        if (null !== $scalar) {
            return ['type' => $scalar];
        }

        if ($type instanceof ListType || $type instanceof MapType) {
            $items = $this->typeSchema($type instanceof ListType ? $type->inner : $type->value, $json);
            if (null === $items) {
                return null;
            }

            return $type instanceof ListType ? ['type' => 'array', 'items' => $items] : ['type' => 'object', 'additionalProperties' => $items];
        }

        return match (true) {
            $type instanceof ShapeType => $this->shapeSchema($type, $json),
            $type instanceof UnionType => $this->unionSchema($type, $json),
            $type instanceof LiteralType => ['enum' => [$type->value]],
            // An enum's ids are its own to say and can't be read statically; that it is a string can.
            $type instanceof IdOfType => ['type' => 'string'],
            default => null,
        };
    }

    /**
     * A union of literals is the values it allows; anything else is either of its members. A `null`
     * member is a value sent as JSON, added as {@see self::withNull()} adds it; in a query string,
     * which cannot carry one, it is dropped.
     *
     * @return array<string, mixed>|null
     */
    private function unionSchema(UnionType $union, bool $json = false): ?array
    {
        $members = [];
        $values = [];
        $null = false;
        foreach ($union->types as $member) {
            if ($member instanceof ScalarType && 'null' === $member->base()) {
                $null = true;

                continue;
            }

            $schema = $this->typeSchema($member, $json);
            if (null === $schema) {
                return null;
            }
            $members[] = $schema;
            if ($member instanceof LiteralType) {
                $values[] = $member->value;
            }
        }

        if ([] !== $members && \count($values) === \count($members)) {
            $schema = ['enum' => array_values(array_unique($values, \SORT_REGULAR))];
        } else {
            $schema = 1 === \count($members) ? $members[0] : ['anyOf' => $members];
        }

        return $json && $null && [] !== $members ? self::withNull($schema) : $schema;
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

    /**
     * A string's Regex constraint as the schema's `pattern`, when it has exactly one and it reads the
     * same in JSON Schema's dialect ({@see self::regexPattern()}). Two must both hold, which one
     * `pattern` cannot say; one that can't be carried over would leave the other claiming to be the
     * whole rule.
     *
     * @param list<Constraint> $constraints
     *
     * @return array{pattern?: string}
     */
    private function patternOf(array $constraints, string $type): array
    {
        if ('string' !== $type) {
            return [];
        }

        $regexes = array_values(array_filter($constraints, static fn (Constraint $constraint): bool => $constraint instanceof Regex));
        if (1 !== \count($regexes)) {
            return [];
        }
        $pattern = self::regexPattern($regexes[0]);

        return null === $pattern ? [] : ['pattern' => $pattern];
    }

    /**
     * A route requirement as a `pattern`: the router matches it against the whole segment, so it is
     * anchored (`^(?:…)$`), after the leading `^`/`\A` and trailing `$`/`\z` Symfony itself strips.
     * Null for a requirement holding a `%…%` container placeholder, which the router resolves and the
     * literal text is not, and for one that does not read the same in JSON Schema's dialect
     * ({@see self::readsTheSameInEcma()}).
     */
    private static function routePattern(string $requirement): ?string
    {
        // Route::sanitizeRequirement(), as Symfony does it.
        if (str_starts_with($requirement, '^')) {
            $requirement = substr($requirement, 1);
        } elseif (str_starts_with($requirement, '\A')) {
            $requirement = substr($requirement, 2);
        }
        if (str_ends_with($requirement, '$')) {
            $requirement = substr($requirement, 0, -1);
        } elseif (str_ends_with($requirement, '\z')) {
            $requirement = substr($requirement, 0, -2);
        }

        if (str_contains($requirement, '%') || !self::readsTheSameInEcma($requirement, alternationOutsideGroups: true)) {
            return null;
        }

        return '^(?:' . $requirement . ')$';
    }

    /**
     * A Regex constraint as a `pattern`, when it means the same there. Only a whole-value rule
     * carries over: anchored at both ends (`/^…$/`) — unanchored, PCRE matches anywhere and across
     * lines, which no padding with `.*` reproduces — with no alternation outside a group (`^a|b$` is
     * `(^a)|(b$)`), no flag but `D` and `u`, a value matched as sent (no normaliser) and a match that
     * must succeed. PCRE's `$` without `D` also matches before a final newline, so the published rule
     * allows one (`\n?$`).
     */
    private static function regexPattern(Regex $regex): ?string
    {
        if (!$regex->match || null !== $regex->normalizer) {
            return null;
        }

        $pattern = $regex->pattern;
        if (null === $pattern) {
            return null;
        }
        $open = $pattern[0] ?? '';
        $close = ['(' => ')', '[' => ']', '{' => '}', '<' => '>'][$open] ?? $open;
        $end = strrpos($pattern, $close);
        if ('' === $open || ctype_alnum($open) || '\\' === $open || false === $end || 0 === $end) {
            return null;
        }

        $flags = substr($pattern, $end + 1);
        if (\strlen($flags) !== strspn($flags, 'Du')) {
            return null;
        }

        $body = substr($pattern, 1, $end - 1);
        // An escaped delimiter is the character itself; escaped, a non-syntax one is an error in
        // Unicode mode. `/` stays escaped, which both read as a slash.
        if ('/' !== $open && !str_contains('^$\.*+?()[]{}|', $open)) {
            $body = str_replace('\\' . $open, $open, $body);
        }

        if (!str_starts_with($body, '^') || !str_ends_with($body, '$') || 1 === strspn(strrev(substr($body, 0, -1)), '\\') % 2) {
            return null;
        }
        $inner = substr($body, 1, -1);

        if (!self::readsTheSameInEcma($inner, alternationOutsideGroups: false)) {
            return null;
        }

        return '^(?:' . $inner . ')' . (str_contains($flags, 'D') ? '' : '\n?') . '$';
    }

    /**
     * Whether a regex fragment reads the same, and compiles, as ECMA-262 in Unicode mode — how Ajv
     * compiles a schema's `pattern`, and an argument check that can't compile refuses every call.
     * Read token by token against what both dialects agree on, rather than searched for what they
     * don't:
     *
     * - printable ASCII literals; outside a class, the syntax characters only escaped;
     * - the escapes `\d \D \w \W \b \B \n \r \t \f` and escaped syntax characters (`\.`, `\/`, …) —
     *   not `\s` or `\v`, whose sets differ, nor a backreference, an octal, `\x`, `\p` or an escaped
     *   letter or `_`;
     * - classes of literals, ranges between two literals in order, and `\d \D \w \W` — no nested
     *   bracket, so no POSIX class, and no range from a class escape (`[\w-.]`);
     * - plain and `(?:` groups, `*`, `+`, `?` and `{n}`, `{n,}`, `{n,m}` (n ≤ m) after something to
     *   repeat, each optionally lazy — not possessive;
     * - no `.` (PCRE's matches `\r`, and a route's a newline too; ECMA's neither), no `^` or `$`
     *   (the caller anchors the whole), and `|` outside a group only where the caller wraps it.
     */
    private static function readsTheSameInEcma(string $fragment, bool $alternationOutsideGroups): bool
    {
        if ('' === $fragment || 1 === preg_match('/[^\x20-\x7E]/', $fragment)) {
            return false;
        }

        $length = \strlen($fragment);
        $depth = 0;
        $repeatable = false;
        $i = 0;
        while ($i < $length) {
            $char = $fragment[$i];

            if ('\\' === $char) {
                $escaped = $fragment[$i + 1] ?? '';
                if ('' !== $escaped && str_contains('dDwWnrtf', $escaped)) {
                    $repeatable = true;
                } elseif ('' !== $escaped && str_contains('bB', $escaped)) {
                    $repeatable = false;
                } elseif ('' !== $escaped && str_contains('^$\.*+?()[]{}|/', $escaped)) {
                    $repeatable = true;
                } else {
                    return false;
                }
                $i += 2;

                continue;
            }

            if ('[' === $char) {
                $close = self::classEnd($fragment, $i);
                if (null === $close) {
                    return false;
                }
                $i = $close + 1;
                $repeatable = true;

                continue;
            }

            if ('(' === $char) {
                if ('?' === ($fragment[$i + 1] ?? '')) {
                    if (':' !== ($fragment[$i + 2] ?? '')) {
                        return false;
                    }
                    $i += 2;
                }
                ++$depth;
                ++$i;
                $repeatable = false;

                continue;
            }

            if (')' === $char) {
                if (0 === $depth) {
                    return false;
                }
                --$depth;
                ++$i;
                $repeatable = true;

                continue;
            }

            if ('|' === $char) {
                if (0 === $depth && !$alternationOutsideGroups) {
                    return false;
                }
                ++$i;
                $repeatable = false;

                continue;
            }

            if (str_contains('*+?{', $char)) {
                if (!$repeatable) {
                    return false;
                }
                if ('{' === $char) {
                    if (1 !== preg_match('/\G\{(\d+)(?:(,)(\d*))?\}/', $fragment, $match, 0, $i)
                        || (isset($match[3]) && '' !== $match[3] && (int) $match[1] > (int) $match[3])) {
                        return false;
                    }
                    $i += \strlen($match[0]);
                } else {
                    ++$i;
                }
                if ('+' === ($fragment[$i] ?? '')) {
                    return false;
                }
                if ('?' === ($fragment[$i] ?? '')) {
                    ++$i;
                }
                $repeatable = false;

                continue;
            }

            if (str_contains('}].^$', $char)) {
                return false;
            }

            ++$i;
            $repeatable = true;
        }

        return 0 === $depth;
    }

    /**
     * Where the class opening at `$open` closes, when every part of it reads the same in both
     * dialects: literals, escaped syntax characters and `\n \r \t \f \b \-`, the class escapes
     * `\d \D \w \W`, and ranges between two literals in order. Null otherwise — a `[` inside (a POSIX
     * class, or nesting), a range from or to a class escape, an empty class.
     */
    private static function classEnd(string $fragment, int $open): ?int
    {
        $length = \strlen($fragment);
        $i = $open + 1;
        if ('^' === ($fragment[$i] ?? '')) {
            ++$i;
        }
        if (']' === ($fragment[$i] ?? '')) {
            return null;
        }

        /** @var list<array{kind: 'literal'|'set'|'dash', char: string}> $atoms */
        $atoms = [];
        while ($i < $length && ']' !== $fragment[$i]) {
            $char = $fragment[$i];
            if ('[' === $char) {
                return null;
            }
            if ('\\' === $char) {
                $escaped = $fragment[$i + 1] ?? '';
                if ('' !== $escaped && str_contains('dDwW', $escaped)) {
                    $atoms[] = ['kind' => 'set', 'char' => $escaped];
                } elseif ('' !== $escaped && str_contains('nrtfb', $escaped)) {
                    $atoms[] = ['kind' => 'literal', 'char' => ['n' => "\n", 'r' => "\r", 't' => "\t", 'f' => "\f", 'b' => "\x08"][$escaped]];
                } elseif ('' !== $escaped && str_contains('^$\.*+?()[]{}|/-', $escaped)) {
                    $atoms[] = ['kind' => 'literal', 'char' => $escaped];
                } else {
                    return null;
                }
                $i += 2;

                continue;
            }
            $atoms[] = ['kind' => '-' === $char ? 'dash' : 'literal', 'char' => $char];
            ++$i;
        }
        if ($i >= $length) {
            return null;
        }

        $count = \count($atoms);
        for ($k = 0; $k < $count; ++$k) {
            if ($k + 2 < $count && 'dash' === $atoms[$k + 1]['kind'] && 'dash' !== $atoms[$k]['kind']) {
                [$from, $to] = [$atoms[$k], $atoms[$k + 2]];
                if ('set' === $from['kind'] || 'set' === $to['kind'] || \ord($from['char']) > \ord($to['char'])) {
                    return null;
                }
                $k += 2;
            }
        }

        return $i;
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
