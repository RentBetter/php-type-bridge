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
    /**
     * @param list<string> $rootSources paths under the source directory whose types are declared
     *                                 in the root module rather than a domain's: a directory
     *                                 covers everything beneath it, a file only itself. For the
     *                                 types every domain shares, so they sit beside the config
     *                                 aliases instead of in a module of their own.
     */
    public function __construct(
        public SegmentCase $segmentCase = SegmentCase::AsIs,
        public ?string $rootModule = null,
        public ImportStrategy $importStrategy = ImportStrategy::RelativeSibling,
        public ?string $aliasBase = null,
        public string $header = '// AUTO-GENERATED. DO NOT EDIT.',
        public SortOrder $declarationOrder = SortOrder::Declared,
        public SortOrder $importOrder = SortOrder::Name,
        public int $domainDepth = 1,
        public array $rootSources = [],
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $allowedKeys = ['segmentCase', 'rootModule', 'importStrategy', 'aliasBase', 'header', 'declarationOrder', 'importOrder', 'domainDepth', 'rootSources'];
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
        $declarationOrder = self::enumOption($config, 'declarationOrder', SortOrder::class, SortOrder::Declared);
        $importOrder = self::enumOption($config, 'importOrder', SortOrder::class, SortOrder::Name);
        $domainDepth = $config['domainDepth'] ?? 1;
        if (!\is_int($domainDepth) || $domainDepth < 1) {
            throw new RuntimeException('TypeBridge output config "domainDepth" must be a positive integer.');
        }

        $rootSources = self::rootSourcesOption($config);
        if ([] !== $rootSources && null === $rootModule) {
            throw new RuntimeException('TypeBridge output config "rootSources" needs a "rootModule" to emit into.');
        }

        if (ImportStrategy::Alias === $importStrategy && null === $aliasBase) {
            throw new RuntimeException('TypeBridge output config "aliasBase" is required when "importStrategy" is "alias".');
        }

        return new self(
            segmentCase: $segmentCase,
            rootModule: $rootModule,
            importStrategy: $importStrategy,
            aliasBase: $aliasBase,
            header: $header,
            declarationOrder: $declarationOrder,
            importOrder: $importOrder,
            domainDepth: $domainDepth,
            rootSources: $rootSources,
        );
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return list<string>
     */
    private static function rootSourcesOption(array $config): array
    {
        $sources = $config['rootSources'] ?? [];
        if (!\is_array($sources) || !array_is_list($sources)) {
            throw new RuntimeException('TypeBridge output config "rootSources" must be a list of paths relative to the source directory.');
        }

        $paths = [];
        foreach ($sources as $source) {
            $path = \is_string($source) ? trim(str_replace('\\', '/', $source), '/') : '';
            if ('' === $path) {
                throw new RuntimeException('TypeBridge output config "rootSources" must be a list of non-empty paths relative to the source directory.');
            }
            $paths[] = $path;
        }

        return $paths;
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
