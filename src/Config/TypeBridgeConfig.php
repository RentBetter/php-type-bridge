<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Config;

use RuntimeException;

/**
 * Top-level TypeBridge configuration. Holds the TypeScript naming rules and
 * the `preserveNull` list (fields where null is meaningful and must be
 * emitted as `field: T | null` rather than `field?: T`).
 */
final readonly class TypeBridgeConfig
{
    /**
     * @param list<string> $preserveNull entries of the form "ShapeName.fieldName"
     *   where the shape name matches the @phpstan-type alias (or class short name
     *   for `_self` shapes). Fields listed here must be annotated `T|null`; all
     *   other nullable fields must be annotated `?T`.
     * @param array<string, string> $requirementTypes route-parameter requirement regex => TS
     *   type, merged over the bundle's Symfony Requirement defaults to refine derived path-param
     *   types (e.g. a project's own short-uuid pattern => "string")
     * @param string|null $mcpScopeAttribute FQCN of the project's auth-scope attribute (e.g. a
     *   TokenAccess attribute). When set, every #[McpTool] endpoint must carry it — its string /
     *   string-backed-enum values are collected into the tool's `scopes` in the MCP manifest,
     *   and generation fails for a tool without one (an unguessable-scope tool would be
     *   uncallable or ungated).
     * @param string|null $mcpScopeProperty name of the one property on that attribute holding the
     *   scopes. Omitted, every public property is read — safe only for an attribute whose
     *   properties are all scopes. Name it when the attribute carries anything else, or those
     *   values are silently collected as scopes too: an `entities` map of route param => entity
     *   class would contribute the class names, producing tools gated on scopes that cannot exist.
     * @param array<string, string> $typeAliases project-wide alias name => TypeScript type (e.g.
     *   "UuidStr" => "string"). Declared here rather than as a @phpstan-type on a class, so a
     *   shape can reference the name without every file importing it.
     */
    public function __construct(
        public TypeScriptNaming $typescript = new TypeScriptNaming(),
        public array $preserveNull = [],
        public OutputStructure $output = new OutputStructure(),
        public array $requirementTypes = [],
        public ?string $mcpScopeAttribute = null,
        public ?string $mcpScopeProperty = null,
        public array $typeAliases = [],
    ) {}

    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new RuntimeException(\sprintf('TypeBridge config file "%s" was not found.', $path));
        }

        $config = require $path;
        if (!is_array($config)) {
            throw new RuntimeException(\sprintf('TypeBridge config file "%s" must return an array.', $path));
        }

        return self::fromArray($config);
    }

    /**
     * @param array<int|string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $allowedKeys = ['typescript', 'preserveNull', 'output', 'requirementTypes', 'mcpScopeAttribute', 'mcpScopeProperty', 'typeAliases'];
        $unknownKeys = array_diff(array_keys($config), $allowedKeys);
        if ([] !== $unknownKeys) {
            $unknown = array_values($unknownKeys);
            sort($unknown);

            throw new RuntimeException(\sprintf(
                'Unknown TypeBridge config keys: %s. Allowed: %s.',
                implode(', ', $unknown),
                implode(', ', $allowedKeys),
            ));
        }

        $typescript = TypeScriptNaming::fromArray(
            self::stringKeyedArray($config['typescript'] ?? [], 'typescript'),
        );
        $preserveNull = self::preserveNullList($config['preserveNull'] ?? []);
        $output = OutputStructure::fromArray(self::stringKeyedArray($config['output'] ?? [], 'output'));
        $requirementTypes = self::requirementTypesMap($config['requirementTypes'] ?? []);
        $mcpScopeAttribute = self::mcpScopeAttributeName($config['mcpScopeAttribute'] ?? null);
        $mcpScopeProperty = self::mcpScopePropertyName($config['mcpScopeProperty'] ?? null, $mcpScopeAttribute);
        $typeAliases = self::typeAliasesMap($config['typeAliases'] ?? []);

        return new self($typescript, $preserveNull, $output, $requirementTypes, $mcpScopeAttribute, $mcpScopeProperty, $typeAliases);
    }

    public function isPreserveNull(string $shapeName, string $fieldName): bool
    {
        return \in_array($shapeName . '.' . $fieldName, $this->preserveNull, strict: true);
    }

    /**
     * @return list<string>
     */
    private static function preserveNullList(mixed $value): array
    {
        if (!is_array($value)) {
            throw new RuntimeException('TypeBridge config key "preserveNull" must be a list of "ShapeName.fieldName" strings.');
        }

        $result = [];
        $expectedIndex = 0;
        foreach ($value as $key => $entry) {
            if ($key !== $expectedIndex) {
                throw new RuntimeException('TypeBridge config key "preserveNull" must be a list (sequential integer keys starting at 0).');
            }
            ++$expectedIndex;

            if (!is_string($entry)) {
                throw new RuntimeException('TypeBridge config key "preserveNull" must contain only strings.');
            }

            if (1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*$/', $entry)) {
                throw new RuntimeException(\sprintf(
                    'TypeBridge config key "preserveNull" entry "%s" must match the format "ShapeName.fieldName".',
                    $entry,
                ));
            }

            $result[] = $entry;
        }

        return $result;
    }

    private static function mcpScopeAttributeName(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!is_string($value) || '' === $value) {
            throw new RuntimeException('TypeBridge config key "mcpScopeAttribute" must be an attribute class name.');
        }

        return $value;
    }

    private static function mcpScopePropertyName(mixed $value, ?string $mcpScopeAttribute): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!is_string($value) || '' === $value) {
            throw new RuntimeException('TypeBridge config key "mcpScopeProperty" must be a property name.');
        }

        if (null === $mcpScopeAttribute) {
            throw new RuntimeException('TypeBridge config key "mcpScopeProperty" names a property on "mcpScopeAttribute", which is not set.');
        }

        return $value;
    }

    /**
     * @return array<string, string>
     */
    private static function requirementTypesMap(mixed $value): array
    {
        if (!is_array($value)) {
            throw new RuntimeException('TypeBridge config key "requirementTypes" must be a map of requirement-regex => TS type.');
        }

        $result = [];
        foreach ($value as $regex => $tsType) {
            if (!is_string($regex) || !is_string($tsType)) {
                throw new RuntimeException('TypeBridge config key "requirementTypes" must map requirement-regex strings to TS type strings.');
            }
            $result[$regex] = $tsType;
        }

        return $result;
    }

    /**
     * Project-wide type aliases: name => TypeScript type. Declared here rather than as a
     * `@phpstan-type` on a class, so a shape can reference a primitive such as `UuidStr`
     * without every file importing it. PHPStan resolves the same names from its own
     * `parameters.typeAliases`; this key is what makes them exist for generation too.
     *
     * @return array<string, string>
     */
    private static function typeAliasesMap(mixed $value): array
    {
        if (!is_array($value)) {
            throw new RuntimeException('TypeBridge config key "typeAliases" must be a map of alias name => TS type.');
        }

        $result = [];
        foreach ($value as $name => $tsType) {
            if (!is_string($name) || !is_string($tsType)) {
                throw new RuntimeException('TypeBridge config key "typeAliases" must map alias-name strings to TS type strings.');
            }
            if (1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                throw new RuntimeException(\sprintf('TypeBridge type alias "%s" is not a valid TypeScript identifier.', $name));
            }
            $result[$name] = $tsType;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private static function stringKeyedArray(mixed $value, string $key): array
    {
        if (!is_array($value)) {
            throw new RuntimeException(\sprintf('TypeBridge config key "%s" must be an array.', $key));
        }

        $normalized = [];
        foreach ($value as $k => $v) {
            if (!is_string($k)) {
                throw new RuntimeException(\sprintf('TypeBridge config key "%s" must be a string-keyed array.', $key));
            }
            $normalized[$k] = $v;
        }

        return $normalized;
    }
}
