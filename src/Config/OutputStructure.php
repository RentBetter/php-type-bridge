<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Config;

use RuntimeException;

/**
 * Describes the on-disk shape of generated TypeScript so different consumers can
 * target their own layout: directory casing, an optional shared root module, how
 * per-domain modules import that root, and the file header banner.
 *
 * Defaults reproduce TypeBridge's historical output (domain dirs verbatim, no root
 * module, sibling-relative imports, the standard banner).
 */
final readonly class OutputStructure
{
    public function __construct(
        public SegmentCase $segmentCase = SegmentCase::AsIs,
        public ?string $rootModule = null,
        public ImportStrategy $importStrategy = ImportStrategy::RelativeSibling,
        public ?string $aliasBase = null,
        public string $header = '// AUTO-GENERATED. DO NOT EDIT.',
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $allowedKeys = ['segmentCase', 'rootModule', 'importStrategy', 'aliasBase', 'header'];
        $unknownKeys = array_diff(array_keys($config), $allowedKeys);
        if ([] !== $unknownKeys) {
            $unknown = array_values($unknownKeys);
            sort($unknown);

            throw new RuntimeException(\sprintf(
                'Unknown TypeBridge output config keys: %s. Allowed: %s.',
                implode(', ', $unknown),
                implode(', ', $allowedKeys),
            ));
        }

        $segmentCase = self::enumOption($config, 'segmentCase', SegmentCase::class, SegmentCase::AsIs);
        $importStrategy = self::enumOption($config, 'importStrategy', ImportStrategy::class, ImportStrategy::RelativeSibling);
        $aliasBase = self::nullableStringOption($config, 'aliasBase');
        $header = self::nullableStringOption($config, 'header') ?? '// AUTO-GENERATED. DO NOT EDIT.';
        $rootModule = self::nullableStringOption($config, 'rootModule');

        if (ImportStrategy::Alias === $importStrategy && null === $aliasBase) {
            throw new RuntimeException('TypeBridge output config "aliasBase" is required when "importStrategy" is "alias".');
        }

        return new self(
            segmentCase: $segmentCase,
            rootModule: $rootModule,
            importStrategy: $importStrategy,
            aliasBase: $aliasBase,
            header: $header,
        );
    }

    /**
     * @template T of \BackedEnum
     * @param array<string, mixed> $config
     * @param class-string<T> $enumClass
     * @param T $default
     * @return T
     */
    private static function enumOption(array $config, string $key, string $enumClass, \BackedEnum $default): \BackedEnum
    {
        if (!array_key_exists($key, $config)) {
            return $default;
        }

        $value = $config[$key];
        if (!is_string($value)) {
            throw new RuntimeException(\sprintf('TypeBridge output config "%s" must be a string.', $key));
        }

        $resolved = $enumClass::tryFrom($value);
        if (null === $resolved) {
            $allowed = array_map(static fn(\BackedEnum $case): int|string => $case->value, $enumClass::cases());

            throw new RuntimeException(\sprintf(
                'TypeBridge output config "%s" must be one of: %s. Got "%s".',
                $key,
                implode(', ', array_map(strval(...), $allowed)),
                $value,
            ));
        }

        return $resolved;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function nullableStringOption(array $config, string $key): ?string
    {
        if (!array_key_exists($key, $config) || null === $config[$key]) {
            return null;
        }

        if (!is_string($config[$key])) {
            throw new RuntimeException(\sprintf('TypeBridge output config "%s" must be a string.', $key));
        }

        return $config[$key];
    }
}
