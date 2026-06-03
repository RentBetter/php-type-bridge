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
     */
    public function __construct(
        public TypeScriptNaming $typescript = new TypeScriptNaming(),
        public array $preserveNull = [],
        public OutputStructure $output = new OutputStructure(),
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
        $allowedKeys = ['typescript', 'preserveNull', 'output'];
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

        return new self($typescript, $preserveNull, $output);
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
