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
            interfacePrefix: $config['interfacePrefix'] ?? '',
            enumValueSuffix: $config['enumValueSuffix'] ?? '',
            enumShapeSuffix: $config['enumShapeSuffix'] ?? 'Data',
            queryAliasSuffix: $config['queryAliasSuffix'] ?? 'Query',
            bodyAliasSuffix: $config['bodyAliasSuffix'] ?? 'Body',
            pathAliasSuffix: $config['pathAliasSuffix'] ?? 'PathParams',
            endpointMapSuffix: $config['endpointMapSuffix'] ?? 'EndpointMap',
            endpointResultSuffix: $config['endpointResultSuffix'] ?? 'Result',
        );
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
