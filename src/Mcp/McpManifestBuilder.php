<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Mcp;

use BackedEnum;
use PTGS\TypeBridge\Config\IncludeConvention;
use PTGS\TypeBridge\Model\CollectedEndpointContract;
use PTGS\TypeBridge\Model\CollectedFormField;
use PTGS\TypeBridge\Model\CollectedInputReference;
use PTGS\TypeBridge\Model\FailedBlank;
use PTGS\TypeBridge\Parser\IdOfType;
use PTGS\TypeBridge\Parser\IntersectionType;
use PTGS\TypeBridge\Parser\ListType;
use PTGS\TypeBridge\Parser\LiteralType;
use PTGS\TypeBridge\Parser\MapType;
use PTGS\TypeBridge\Parser\NameRefType;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\ParsedType;
use PTGS\TypeBridge\Parser\ScalarType;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Parser\UnionType;
use PTGS\TypeBridge\Parser\ValueOfType;
use PTGS\TypeBridge\Support\AttributeText;
use PTGS\TypeBridge\Support\ClassNames;
use PTGS\TypeBridge\Support\HeldConstraints;
use PTGS\TypeBridge\Support\PhpFileClassLocator;
use ReflectionProperty;
use Throwable;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThan;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use WeakMap;
use stdClass;

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
 * request that makes something — requires a field its constraints refuse blank (a NotBlank, a NotNull,
 * a Count — whatever refuses what its form makes of a value left out), since a contract the request
 * shares with an update declares every key optional, and a model would
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
     * What a field's blank value is asked of: Symfony's own validator.
     */
    private ?ValidatorInterface $validator = null;

    /** @var WeakMap<CollectedFormField, bool>|null by field, whether its blank value is refused: asked once */
    private ?WeakMap $blankRefused = null;

    /**
     * @param IncludeConvention  $includes          whose `query` parameters every tool with a response body takes
     * @param AttributeText|null $paramDescriptions the project's parameter-documentation attribute, read off
     *                                              the data-class property each argument binds
     */
    /**
     * @param array<string, string> $typeAliases the project-wide aliases, name => TypeScript: a key
     *        typed with one is the JSON type its TypeScript is, where that is a primitive
     */
    public function __construct(
        private readonly IncludeConvention $includes = new IncludeConvention(),
        private readonly ?AttributeText $paramDescriptions = null,
        private readonly array $typeAliases = [],
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
                // A path segment is always required. A string's route requirement says what it must
                // look like, so a model learns a ticket is `SO-12` before the router's 404.
                $properties[$param->name] = ['type' => $param->schemaType()];
                $pattern = 'string' === $param->schemaType() && null !== $param->requirement ? PortableRegex::routePattern($param->requirement, $param->utf8) : null;
                if (null !== $pattern) {
                    $properties[$param->name]['pattern'] = $pattern;
                }
                $required[] = $param->name;
            }

            $query = [];
            $body = [];
            $this->addFields($request->query, $query, $required);
            $this->addFields($request->body, $body, $required, makes: 'POST' === strtoupper($httpMethod), json: true);
            // One argument of a name fills a path param and a field alike: it holds to both.
            foreach ([$query, $body] as $fields) {
                foreach ($fields as $name => $schema) {
                    $properties[$name] = isset($properties[$name]) ? ['allOf' => [$properties[$name], $schema]] : $schema;
                }
            }
        }

        foreach ($this->includeQuery($contract) as $name => $description) {
            $properties[$name] ??= ['type' => 'string', 'description' => $description];
        }

        // An endpoint with no inputs is a bare `{type: object}`: an empty PHP array would
        // encode as a JSON array, and JSON Schema requires `properties` to be an object.
        $schema = ['type' => 'object'];
        if ([] !== $properties) {
            $schema['properties'] = self::properties($properties);
        }
        if ([] !== $required) {
            // Each name once: a path param and a body field may share one, and the meta-schema
            // holds `required` to unique items.
            $schema['required'] = array_values(array_unique($required));
        }

        return $schema;
    }

    /**
     * The fields as arguments, each with the key its contract declares: its own, or for an input
     * extending a shape (`Base & array{…}`), one it inherits, read where that shape is declared, the
     * two read as the intersection reads them ({@see CollectedInputReference::keys()}).
     *
     * @param array<array-key, array<string, mixed>> $properties
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

        $declared = self::shapeKeys($reference->keys());
        foreach ($reference->fields as $field) {
            $key = $declared[$field->name] ?? null;
            $properties[$field->name] = $this->fieldSchema($field, $key?->type, $json, $makes);
            if ($json && $this->takesNull($key, $field)) {
                $properties[$field->name] = self::withNull($properties[$field->name]);
            }
            if ($this->isRequired($field, $key, inQuery: !$json) || ($makes && $this->refusesBlank($field))) {
                $required[] = $field->name;
            }
        }
    }

    /**
     * Whether a submitted value is refused blank or missing. A field left out, sent as null or sent
     * blank reaches validation as its form makes it ({@see CollectedFormField::$blankValue}) — null,
     * '', an empty list, an unticked box — and whether that is refused is its constraints' to say,
     * asked of Symfony's validator: a NotNull refuses a null and lets '' through, a NotBlank refuses
     * an empty list though it allows a null, a Count or a Length refuses too few.
     */
    private function refusesBlank(CollectedFormField $field): bool
    {
        $this->blankRefused ??= new WeakMap();

        return $this->blankRefused[$field] ??= $this->refusesBlankValue($field->constraints, $field->blankValue);
    }

    /**
     * Whether a blank reaching validation as the value is refused: one the form can't make a value
     * of ({@see FailedBlank}) always is; any other where a constraint refuses it.
     *
     * @param list<Constraint> $constraints
     */
    private function refusesBlankValue(array $constraints, mixed $value): bool
    {
        return $value instanceof FailedBlank || $this->refuses($constraints, $value);
    }

    /**
     * Whether any of the constraints refuses the value, nested ones included. One that can't be asked
     * of the value alone — it reads the object the value is on, or its validator is a service the
     * builder has none of — says nothing here.
     *
     * @param list<Constraint> $constraints
     */
    private function refuses(array $constraints, mixed $value): bool
    {
        $this->validator ??= Validation::createValidator();
        foreach ($constraints as $constraint) {
            try {
                if (\count($this->validator->validate($value, $constraint)) > 0) {
                    return true;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return false;
    }

    /**
     * Whether the key takes null as a value: the contract admits a null that is sent and means
     * something — an update's "clear this" — and its form and validation do not refuse it.
     */
    private function takesNull(?ShapeField $key, CollectedFormField $field): bool
    {
        if (null === $key || !ShapeField::admitsNull($key->type)) {
            return false;
        }

        // An embedded form is never set to null: Symfony submits a compound form's children, and a
        // null sent for the form is an empty array that clears each of them — a 422 from a child's
        // NotBlank, or a record of nulls. A list's null is an empty list, which does clear it; and
        // a form that takes a record's id as well (`string|array{…}|null`) reads what is sent
        // itself, before it is submitted, so its null is the "none" it declares.
        if ($field->compound && [] !== $field->children && [] === $this->declaredScalars($key->type)) {
            return false;
        }

        // A contract one create and one update share says `string|null`; a NotNull, or a NotBlank
        // that does not allow null, still refuses it with a 422.
        return !$this->refusesBlank($field);
    }

    /**
     * The schema with null among what it accepts: a `null` type beside its own (as a type list, or
     * in `anyOf` where it has no type of its own), and in its `enum` when it lists its values, which
     * would refuse null otherwise. One that accepts null already is as it was.
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private static function withNull(array $schema): array
    {
        if (!isset($schema['type']) && isset($schema['anyOf']) && \is_array($schema['anyOf'])) {
            if (!\in_array(['type' => 'null'], $schema['anyOf'], true)) {
                $schema['anyOf'][] = ['type' => 'null'];
            }

            return $schema;
        }

        if (isset($schema['type']) && \is_string($schema['type'])) {
            $schema['type'] = [$schema['type'], 'null'];
        }
        if (isset($schema['enum']) && \is_array($schema['enum']) && !\in_array(null, $schema['enum'], true)) {
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
    private function isRequired(CollectedFormField $field, ?ShapeField $key, bool $inQuery): bool
    {
        return null === $key ? $field->required : !$key->mayBeLeftOut($inQuery);
    }

    /**
     * The field's schema, with what its validation bounds it by and what its property says it is.
     *
     * @param ParsedType|null $declared the type the request contract gives this field, if it names it
     * @param bool            $json     whether it is sent as JSON, which can carry a null
     * @param bool            $makes    whether the request makes something, so a field nested in it that
     *                                  its constraints refuse blank is one it can't go without
     *
     * @return array<string, mixed>
     */
    private function fieldSchema(CollectedFormField $field, ?ParsedType $declared = null, bool $json = false, bool $makes = false): array
    {
        $schema = $this->valueSchema($field, $declared, $json, $makes);
        $always = HeldConstraints::always($field->constraints);
        $bounds = $this->bounds($always, $schema['type']);
        $pattern = $this->patternOf($field, $schema['type'], $always);
        if (isset($pattern['minLength'])) {
            $bounds['minLength'] = max($bounds['minLength'] ?? 0, $pattern['minLength']);
            unset($pattern['minLength']);
        }
        // A blank the server takes is submitted null, which a Length skips: its minimum holds the
        // values that aren't blank.
        if ('string' === $schema['type'] && ($bounds['minLength'] ?? 0) > 0 && !$this->refusesBlank($field)) {
            $minimum = $bounds['minLength'];
            unset($bounds['minLength']);
            $bounds = ['anyOf' => [['maxLength' => 0], ['minLength' => $minimum]], ...$bounds];
        }
        $schema = [...$schema, ...$bounds, ...$pattern];

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
    private function valueSchema(CollectedFormField $field, ?ParsedType $declared, bool $json = false, bool $makes = false): array
    {
        // A collection is compound too, but its shape is its entry's, repeated.
        if (null !== $field->entryTypeClass) {
            return ['type' => 'array', 'items' => $this->entrySchema($field, self::listItem($declared), $json, $makes)];
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
        // One whose choices are loaded later — an EntityType's — still takes a list of the values it
        // reads, strings as the form reads them.
        if ($field->multiple) {
            return ['type' => 'array', 'items' => ['type' => 'string']];
        }

        // Its fields held to the keys its key's type declares — or, where that names its data class,
        // the class's own.
        if ($field->compound && [] !== $field->children) {
            return $this->objectSchema($field->children, self::shapeKeys($declared) ?: self::shapeKeys($field->contract), $json, $makes);
        }

        // A compound field with no children reads what is sent whole, so its form says nothing of
        // what it takes: the shape its data class declares does, where every part of it can be said;
        // an object, where some can't.
        if (null !== $field->contract) {
            return $this->shapeSchema($field->contract, $json) ?? ['type' => 'object'];
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
        // `?Alias` of an alias of `T|null` is a nullable of a nullable: its scalar is what both wrap.
        while ($declared instanceof NullableType) {
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
     * A declared shape as JSON Schema, or null when any part of it names a type stated elsewhere
     * (an alias, a generic, a class constant) that the builder cannot read here.
     *
     * @return array{type: 'object', properties?: array<array-key, array<string, mixed>>|stdClass, required?: list<string>}|null
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
            if (!$key->mayBeLeftOut(inQuery: !$json)) {
                $required[] = $key->name;
            }
        }

        $schema = ['type' => 'object'];
        if ([] !== $properties) {
            $schema['properties'] = self::properties($properties);
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
            // A backed enum's values are what it takes.
            $type instanceof ValueOfType => PhpFileClassLocator::loadsEnum($type->enumClass) && is_subclass_of($type->enumClass, BackedEnum::class)
                ? ['enum' => array_map(static fn (BackedEnum $case): int|string => $case->value, $type->enumClass::cases())]
                : null,
            $type instanceof NameRefType && null === $type->class => $this->configAliasSchema($type->name),
            default => null,
        };
    }

    /**
     * A project-wide alias as the JSON type its TypeScript is, through any alias it names in turn:
     * `string`, `number` or `boolean`; null where it is anything else, or no such alias.
     *
     * @return array{type: string}|null
     */
    private function configAliasSchema(string $name): ?array
    {
        $type = $this->typeAliases[$name] ?? null;
        for ($depth = 0; null !== $type && isset($this->typeAliases[trim($type)]) && $depth < 8; ++$depth) {
            $type = $this->typeAliases[trim($type)];
        }

        return match (null === $type ? null : trim($type)) {
            'string' => ['type' => 'string'],
            'number' => ['type' => 'number'],
            'boolean' => ['type' => 'boolean'],
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
            if (ScalarType::isNull($member)) {
                $null = true;

                continue;
            }

            $schema = $this->typeSchema($member, $json);
            if (null === $schema) {
                return null;
            }
            $members[] = $schema;
            // Each value once, told apart as JSON tells them: '1' and 1, true and 1, are two.
            if ($member instanceof LiteralType && !\in_array($member->value, $values, true)) {
                $values[] = $member->value;
            }
        }

        if ([] !== $members && array_all($union->types, static fn (ParsedType $member): bool => $member instanceof LiteralType || ScalarType::isNull($member))) {
            $schema = ['enum' => $values];
        } else {
            $schema = 1 === \count($members) ? $members[0] : ['anyOf' => $members];
        }

        return $json && $null && [] !== $members ? self::withNull($schema) : $schema;
    }

    /**
     * The JSON Schema type of a scalar the contract declares. A form type's name is a guess at
     * this (a CheckboxType binds a boolean and says so nowhere in its name); the contract states it.
     */
    private function declaredScalar(?ParsedType $declared): ?string
    {
        while ($declared instanceof NullableType) {
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
     * A collection's entry: its type — the scalar the contract declares it, as for a field, its form
     * type's name a guess at it otherwise — or for a compound entry its fields, each taking null,
     * sent as JSON, where the entry's declared shape types it `T|null`.
     *
     * @param ParsedType|null $item the type the contract gives an entry (`list<T>`'s T), if it does
     *
     * @return array<string, mixed>
     */
    private function entrySchema(CollectedFormField $field, ?ParsedType $item = null, bool $json = false, bool $makes = false): array
    {
        // An entry its type reads whole is the shape the list declares of it — an object, where its
        // class's shape can't be said here.
        $shape = ShapeType::declaredBy(self::soleMember($item, ShapeType::class) ?? self::soleMember($item, IntersectionType::class));
        if ([] === $field->entryChildren && (null !== $shape || null !== $field->entryDataClass)) {
            return (null === $shape ? null : $this->shapeSchema($shape, $json)) ?? ['type' => 'object'];
        }

        if ([] === $field->entryChildren) {
            \assert(null !== $field->entryTypeClass);
            $schema = ['type' => $this->declaredScalar($item) ?? $this->scalarTypeOf($field->entryTypeClass)];

            // An entry takes null where its contract admits one and nothing it is held to refuses it,
            // as the entry reaches validation: null, '' where its options keep a blank a string,
            // false for a checkbox.
            return $json && null !== $item && ShapeField::admitsNull($item) && !$this->refusesBlankValue($field->entryConstraints, $field->entryBlankValue)
                ? self::withNull($schema)
                : $schema;
        }

        return $this->objectSchema($field->entryChildren, self::shapeKeys($item), $json, $makes);
    }

    /**
     * A compound form as an object: each field with the type its declared key gives it and, sent as
     * JSON, null where that key takes one; required as at the top — where its key says so, or the
     * form where it declares none, or where a request that makes something can't go without it.
     *
     * @param list<CollectedFormField> $children
     * @param array<string, ShapeField> $keys the keys the compound's declared shape names
     *
     * @return array{type: 'object', properties: array<array-key, array<string, mixed>>|stdClass, required?: list<string>}
     */
    private function objectSchema(array $children, array $keys, bool $json, bool $makes): array
    {
        $properties = [];
        $required = [];
        foreach ($children as $child) {
            $key = $keys[$child->name] ?? null;
            $schema = $this->fieldSchema($child, $key?->type, $json, $makes);
            $properties[$child->name] = $json && $this->takesNull($key, $child) ? self::withNull($schema) : $schema;
            if ($this->isRequired($child, $key, inQuery: !$json) || ($makes && $this->refusesBlank($child))) {
                $required[] = $child->name;
            }
        }

        $schema = ['type' => 'object', 'properties' => self::properties($properties)];
        if ([] !== $required) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * Properties by name, as JSON reads them: keys PHP holds as 0, 1, … — named by numbers — would be
     * written as a list, which `properties` can't be.
     *
     * @param array<array-key, array<string, mixed>> $properties
     *
     * @return array<array-key, array<string, mixed>>|stdClass
     */
    private static function properties(array $properties): array|stdClass
    {
        return array_is_list($properties) && [] !== $properties ? (object) $properties : $properties;
    }

    /**
     * The keys of the shape a type declares, by name — itself, or the one shape of a nullable or a
     * union; an intersection's own, where what it extends can't be read, as the top level reads them
     * ({@see ShapeType::declaredBy()}).
     *
     * @return array<string, ShapeField>
     */
    private static function shapeKeys(?ParsedType $type): array
    {
        $keys = [];
        foreach (ShapeType::declaredBy(self::soleMember($type, ShapeType::class) ?? self::soleMember($type, IntersectionType::class))->fields ?? [] as $key) {
            $keys[$key->name] = $key;
        }

        return $keys;
    }

    /**
     * The type of a list's items — of the list itself, or the one list of a nullable or a union.
     */
    private static function listItem(?ParsedType $type): ?ParsedType
    {
        return self::soleMember($type, ListType::class)?->inner;
    }

    /**
     * The type itself if it is a `$kind`, or the one `$kind` among a nullable's or a union's
     * members — none when there are two to choose between.
     *
     * @template T of ParsedType
     *
     * @param class-string<T> $kind
     *
     * @return T|null
     */
    private static function soleMember(?ParsedType $type, string $kind): ?ParsedType
    {
        while ($type instanceof NullableType) {
            $type = $type->inner;
        }
        if ($type instanceof UnionType) {
            $members = array_values(array_filter($type->types, static fn (ParsedType $member): bool => $member instanceof $kind));
            $type = 1 === \count($members) ? $members[0] : null;
        }

        return $type instanceof $kind ? $type : null;
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
     * whole rule. Counted wherever they are nested on the value ({@see HeldConstraints}): one in a
     * Sequentially or a Compound holds as one beside them, and one among an AtLeastOneOf's
     * alternatives or a When's may not hold at all, so is no pattern — and one more to count.
     *
     * A blank the server refuses the rule may match all the same (`^[A-Z]*$` under a NotBlank): a
     * minimum length of 1 refuses it too. A lookahead (`(?!$)`) would, but RE2 and JSON Schema's
     * interoperable subset don't read one.
     *
     * @param list<Constraint> $always the field's constraints that always hold ({@see HeldConstraints::always()})
     *
     * @return array{pattern?: string, minLength?: int}
     */
    private function patternOf(CollectedFormField $field, string $type, array $always): array
    {
        if ('string' !== $type) {
            return [];
        }

        $regexes = array_values(array_filter(HeldConstraints::any($field->constraints), static fn (Constraint $constraint): bool => $constraint instanceof Regex));
        $regex = $regexes[0] ?? null;
        if (1 !== \count($regexes) || null === $regex?->pattern || !\in_array($regex, $always, true)) {
            return [];
        }
        $pattern = $this->regexPattern($regex, $field);
        if (null === $pattern) {
            return [];
        }

        return $this->refusesBlank($field) && 1 === preg_match($regex->pattern, '') ? ['pattern' => $pattern, 'minLength' => 1] : ['pattern' => $pattern];
    }

    /**
     * A Regex constraint as a `pattern`, describing every value the server takes. Only a whole-value
     * rule both dialects read alike carries over ({@see PortableRegex::wholeValueRule()}), with the
     * value matched as it was sent (no normaliser, and no transformer of the form's: the rule reads
     * what that makes of the value) and a match that must succeed.
     *
     * Around the rule goes what reaches it. The form trims the value first (its `trim` option, on by
     * default), so the pattern is the rule on a trimmed value — refusing padding the server would
     * take off — unless the rule could match the padding itself. A blank value is let through where
     * nothing refuses it (refusesBlank()). And PCRE's `$` without `D` also matches before a final
     * newline, which trimming leaves none of.
     */
    private function regexPattern(Regex $regex, CollectedFormField $field): ?string
    {
        if (!$regex->match || null !== $regex->normalizer || $field->transformsValue) {
            return null;
        }

        $rule = null === $regex->pattern ? null : PortableRegex::wholeValueRule($regex->pattern);
        if (null === $rule) {
            return null;
        }
        [$inner, $flags] = $rule;

        // The rule as it reads the trimmed value. A padded value the server would trim is refused —
        // costing a caller nothing — rather than allowed with `\p{…}` padding, which only
        // Unicode-mode regex reads. Where the rule could match the padding itself (a space, a control
        // character, a negated class), a padded value it accepts may trim to one it doesn't, so there
        // is no pattern.
        if ($field->trim && PortableRegex::matchesPadding($inner)) {
            return null;
        }

        // A trimmed value has no final newline for `$` to match before.
        $newline = $field->trim || str_contains($flags, 'D') ? '' : '\n?';
        $value = '(?:' . $inner . ')' . $newline;
        // A blank value reaches the validator as null, trimmed or not, and the Regex lets it through.
        if (!$this->refusesBlank($field)) {
            $value = '' === $newline ? $value . '?' : '(?:' . $value . ')?';
        }

        return '^' . $value . '$';
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
        $shortName = ClassNames::short($formTypeClass);

        return match (true) {
            str_contains($shortName, 'Boolean') => 'boolean',
            str_contains($shortName, 'Integer') => 'integer',
            str_contains($shortName, 'Number'), str_contains($shortName, 'Money') => 'number',
            default => 'string',
        };
    }
}
