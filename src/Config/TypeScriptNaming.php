<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Config;

use RuntimeException;

final readonly class TypeScriptNaming
{
    public function __construct(
        public string $interfacePrefix = '',
        public string $enumValueSuffix = '',
        public string $enumShapeSuffix = 'Data',
        public string $queryAliasSuffix = 'Query',
        public string $bodyAliasSuffix = 'Body',
        public string $pathAliasSuffix = 'PathParams',
        public string $endpointMapSuffix = 'EndpointMap',
        public string $endpointResultSuffix = 'Result',
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $allowedKeys = [
            'interfacePrefix',
            'enumValueSuffix',
            'enumShapeSuffix',
            'queryAliasSuffix',
            'bodyAliasSuffix',
            'pathAliasSuffix',
            'endpointMapSuffix',
            'endpointResultSuffix',
        ];

        $unknownKeys = array_diff(array_keys($config), $allowedKeys);
        if ([] !== $unknownKeys) {
            sort($unknownKeys);

            throw new RuntimeException(\sprintf(
                'Unknown TypeScript naming config keys: %s.',
                implode(', ', $unknownKeys),
            ));
        }

        foreach ($allowedKeys as $key) {
            if (!array_key_exists($key, $config)) {
                continue;
            }

            if (!is_string($config[$key])) {
                throw new RuntimeException(\sprintf(
                    'TypeScript naming config key "%s" must be a string.',
                    $key,
                ));
            }
        }

        return new self(
            interfacePrefix: self::stringOption($config, 'interfacePrefix', ''),
            enumValueSuffix: self::stringOption($config, 'enumValueSuffix', ''),
            enumShapeSuffix: self::stringOption($config, 'enumShapeSuffix', 'Data'),
            queryAliasSuffix: self::stringOption($config, 'queryAliasSuffix', 'Query'),
            bodyAliasSuffix: self::stringOption($config, 'bodyAliasSuffix', 'Body'),
            pathAliasSuffix: self::stringOption($config, 'pathAliasSuffix', 'PathParams'),
            endpointMapSuffix: self::stringOption($config, 'endpointMapSuffix', 'EndpointMap'),
            endpointResultSuffix: self::stringOption($config, 'endpointResultSuffix', 'Result'),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function stringOption(array $config, string $key, string $default): string
    {
        $value = $config[$key] ?? $default;
        if (!is_string($value)) {
            throw new RuntimeException(\sprintf(
                'TypeScript naming config key "%s" must be a string.',
                $key,
            ));
        }

        return $value;
    }

    public function interfaceName(string $name): string
    {
        return $this->interfacePrefix . $name;
    }

    public function enumValueName(string $name): string
    {
        return $name . $this->enumValueSuffix;
    }

    public function enumShapeName(string $name): string
    {
        return $name . $this->enumShapeSuffix;
    }

    public function queryAliasName(string $endpointName): string
    {
        return $endpointName . $this->queryAliasSuffix;
    }

    public function bodyAliasName(string $endpointName): string
    {
        return $endpointName . $this->bodyAliasSuffix;
    }

    public function pathAliasName(string $endpointName): string
    {
        return $endpointName . $this->pathAliasSuffix;
    }

    public function endpointMapName(string $endpointName): string
    {
        return $endpointName . $this->endpointMapSuffix;
    }

    public function endpointResultName(string $endpointName): string
    {
        return $endpointName . $this->endpointResultSuffix;
    }
}
