<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

use PTGS\TypeBridge\Model\CollectedApiResponseClass;
use PTGS\TypeBridge\Model\CollectedDomain;
use PTGS\TypeBridge\Model\CollectedEndpointContract;
use PTGS\TypeBridge\Model\CollectedInputReference;
use PTGS\TypeBridge\Model\CollectedResponseProperty;
use PTGS\TypeBridge\Model\CollectedType;
use PTGS\TypeBridge\Model\ImportedType;
use PTGS\TypeBridge\Parser\IntersectionType;
use PTGS\TypeBridge\Parser\ListType;
use PTGS\TypeBridge\Parser\NameRefType;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\ParsedType;
use PTGS\TypeBridge\Parser\ScalarType;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Parser\UnionType;
use PTGS\TypeBridge\Parser\ValueOfType;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;
use RuntimeException;

final class TypeScriptEmitter
{
    /** @var array<string, CollectedDomain> */
    private array $domains = [];

    /** @var array<string, list<CollectedApiResponseClass>> */
    private array $responses = [];

    /** @var array<string, list<CollectedEndpointContract>> */
    private array $contracts = [];

    public function __construct(
        private readonly EnumResolver $enumResolver,
        private readonly DomainMapper $domainMapper,
    ) {}

    /**
     * @param array<string, CollectedDomain> $domains
     * @param array<string, list<CollectedApiResponseClass>> $responses
     * @param array<string, list<CollectedEndpointContract>> $contracts
     * @return array<string, string>
     */
    public function emit(array $domains, array $responses = [], array $contracts = []): array
    {
        $this->domains = $domains;
        $this->responses = $responses;
        $this->contracts = $contracts;

        $allDomains = array_unique(array_merge(array_keys($domains), array_keys($responses), array_keys($contracts)));
        sort($allDomains);

        $output = [];
        foreach ($allDomains as $domain) {
            $output[$domain] = $this->emitDomain(
                $domain,
                $domains[$domain] ?? new CollectedDomain($domain),
                $responses[$domain] ?? [],
                $contracts[$domain] ?? [],
            );
        }

        return $output;
    }

    /**
     * @param list<CollectedApiResponseClass> $responses
     * @param list<CollectedEndpointContract> $contracts
     */
    private function emitDomain(
        string $domain,
        CollectedDomain $collected,
        array $responses,
        array $contracts,
    ): string {
        $lines = [];
        $lines[] = '// AUTO-GENERATED. DO NOT EDIT.';
        $lines[] = '';

        $imports = $this->collectExternalImports($domain, $collected, $responses, $contracts);
        foreach ($imports as $importDomain => $symbols) {
            sort($symbols);
            $symbols = array_values(array_unique($symbols));
            $lines[] = \sprintf(
                "import type { %s } from '%s';",
                implode(', ', $symbols),
                $this->domainMapper->getRelativeImportPath($domain, $importDomain),
            );
        }
        if ([] !== $imports) {
            $lines[] = '';
        }

        $localEnums = $this->collectLocalEnums($domain, $collected, $responses);
        if ([] !== $localEnums) {
            $lines[] = '// Enums';
            foreach ($localEnums as $enumClass) {
                $values = array_map(
                    static fn(string $value): string => "'" . $value . "'",
                    $this->enumResolver->resolve($enumClass),
                );
                $lines[] = \sprintf(
                    'export type %s = %s;',
                    $this->enumResolver->getShortName($enumClass),
                    implode(' | ', $values),
                );
            }
            $lines[] = '';
        }

        if ([] !== $collected->types) {
            $lines[] = '// Shapes';
            foreach ($collected->types as $type) {
                $this->emitCollectedType($type, $lines);
                $lines[] = '';
            }
        }

        if ([] !== $responses) {
            $lines[] = '// Responses';
            foreach ($responses as $response) {
                $this->emitResponse($response, $lines);
                $lines[] = '';
            }
        }

        $requestContracts = array_values(array_filter(
            $contracts,
            static fn(CollectedEndpointContract $contract): bool => null !== $contract->request && $contract->request->hasAnyInput(),
        ));

        if ([] !== $requestContracts) {
            $lines[] = '// Endpoint inputs';
            foreach ($requestContracts as $contract) {
                if (null !== $contract->request->query) {
                    $lines[] = \sprintf(
                        'export type %sQuery = %s;',
                        $contract->name,
                        $contract->request->query->typeName,
                    );
                }

                if (null !== $contract->request->body) {
                    $lines[] = \sprintf(
                        'export type %sBody = %s;',
                        $contract->name,
                        $contract->request->body->typeName,
                    );
                }

                if (null !== $contract->request->path) {
                    $lines[] = \sprintf(
                        'export type %sPathParams = %s;',
                        $contract->name,
                        $contract->request->path->typeName,
                    );
                }

                $lines[] = '';
            }
        }

        if ([] !== $contracts) {
            $lines[] = '// Endpoint results';
            $lines[] = 'export type EndpointResult<M extends Record<number, unknown>> = {';
            $lines[] = '  [S in keyof M & number]: {';
            $lines[] = '    ok: S extends 200 | 201 | 202 | 204 ? true : false;';
            $lines[] = '    status: S;';
            $lines[] = '    data: M[S];';
            $lines[] = '  };';
            $lines[] = '}[keyof M & number];';
            $lines[] = '';

            foreach ($contracts as $contract) {
                $mapName = $contract->name . 'EndpointMap';
                $resultName = $contract->name . 'Result';
                $lines[] = \sprintf('export type %s = {', $mapName);

                $responses = $contract->responses;
                usort($responses, static fn(CollectedApiResponseClass $left, CollectedApiResponseClass $right): int => $left->status <=> $right->status);
                foreach ($responses as $response) {
                    $lines[] = \sprintf('  %d: %s;', $response->status, $response->name);
                }

                $lines[] = '};';
                $lines[] = \sprintf('export type %s = EndpointResult<%s>;', $resultName, $mapName);
                $lines[] = '';
            }
        }

        return rtrim(implode("\n", $lines)) . "\n";
    }

    /**
     * @param list<string> $lines
     */
    private function emitCollectedType(CollectedType $type, array &$lines): void
    {
        if ($type->parsed instanceof IntersectionType) {
            $lines[] = \sprintf('export interface %s extends %s {', $type->name, $this->typeToTs($type->parsed->base));
            foreach ($type->parsed->extra->fields as $field) {
                $this->emitShapeField($field, $lines);
            }
            $lines[] = '}';

            return;
        }

        if ($type->parsed instanceof ShapeType) {
            $lines[] = \sprintf('export interface %s {', $type->name);
            foreach ($type->parsed->fields as $field) {
                $this->emitShapeField($field, $lines);
            }
            $lines[] = '}';

            return;
        }

        $lines[] = \sprintf('export type %s = %s;', $type->name, $this->typeToTs($type->parsed));
    }

    /**
     * @param list<string> $lines
     */
    private function emitResponse(CollectedApiResponseClass $response, array &$lines): void
    {
        if (204 === $response->status && [] === $response->properties) {
            $lines[] = \sprintf('export type %s = null;', $response->name);

            return;
        }

        $lines[] = \sprintf('export interface %s {', $response->name);
        foreach ($response->properties as $property) {
            $optional = $property->optional ? '?' : '';
            $lines[] = \sprintf(
                '  %s%s: %s;',
                $property->name,
                $optional,
                $this->typeToTs($property->parsed),
            );
        }
        $lines[] = '}';
    }

    /**
     * @param list<string> $lines
     */
    private function emitShapeField(ShapeField $field, array &$lines): void
    {
        $optional = $field->optional;
        $type = $field->type;
        if ($type instanceof NullableType && $type->optional) {
            $optional = true;
            $type = $type->inner;
        }

        $lines[] = \sprintf('  %s%s: %s;', $field->name, $optional ? '?' : '', $this->typeToTs($type));
    }

    private function typeToTs(ParsedType $type): string
    {
        if ($type instanceof ScalarType) {
            return match ($type->type) {
                'string' => 'string',
                'int', 'float', 'numeric' => 'number',
                'bool' => 'boolean',
                'mixed' => 'unknown',
                'null' => 'null',
                default => throw new RuntimeException(\sprintf('Unknown scalar type "%s".', $type->type)),
            };
        }

        if ($type instanceof NullableType) {
            if ($type->optional) {
                return $this->typeToTs($type->inner);
            }

            return $this->typeToTs($type->inner) . ' | null';
        }

        if ($type instanceof ListType) {
            return $this->typeToTs($type->inner) . '[]';
        }

        if ($type instanceof ValueOfType) {
            return $this->enumResolver->getShortName($type->enumClass);
        }

        if ($type instanceof NameRefType) {
            return $type->name;
        }

        if ($type instanceof ShapeType) {
            $fields = array_map(function (ShapeField $field): string {
                $optional = $field->optional;
                $type = $field->type;
                if ($type instanceof NullableType && $type->optional) {
                    $optional = true;
                    $type = $type->inner;
                }

                return \sprintf('%s%s: %s', $field->name, $optional ? '?' : '', $this->typeToTs($type));
            }, $type->fields);

            return '{ ' . implode('; ', $fields) . ' }';
        }

        if ($type instanceof UnionType) {
            return implode(' | ', array_map(fn(ParsedType $member): string => $this->typeToTs($member), $type->types));
        }

        if ($type instanceof IntersectionType) {
            return $this->typeToTs($type->base) . ' & ' . $this->typeToTs($type->extra);
        }

        throw new RuntimeException(\sprintf('Unhandled parsed type "%s".', $type::class));
    }

    /**
     * @param list<CollectedApiResponseClass> $responses
     * @param list<CollectedEndpointContract> $contracts
     * @return array<string, list<string>>
     */
    private function collectExternalImports(string $domain, CollectedDomain $collected, array $responses, array $contracts): array
    {
        $imports = [];

        foreach ($collected->types as $type) {
            $this->appendImportedTypes($domain, $type->imports, $imports);
            $this->appendExternalEnums($domain, $type->parsed, $imports);
        }

        foreach ($responses as $response) {
            $this->appendImportedTypes($domain, $response->imports, $imports);
            foreach ($response->properties as $property) {
                $this->appendExternalEnums($domain, $property->parsed, $imports);
            }
        }

        foreach ($contracts as $contract) {
            if (null === $contract->request) {
                continue;
            }

            foreach ([$contract->request->query, $contract->request->body, $contract->request->path] as $input) {
                if (null === $input) {
                    continue;
                }

                $this->appendInputReferenceImport($domain, $input, $imports);
            }
        }

        ksort($imports);

        return $imports;
    }

    /**
     * @param list<CollectedApiResponseClass> $responses
     * @return list<class-string>
     */
    private function collectLocalEnums(string $domain, CollectedDomain $collected, array $responses): array
    {
        $enumClasses = [];

        foreach ($collected->types as $type) {
            $this->appendLocalEnums($domain, $type->parsed, $enumClasses);
        }

        foreach ($responses as $response) {
            foreach ($response->properties as $property) {
                $this->appendLocalEnums($domain, $property->parsed, $enumClasses);
            }
        }

        sort($enumClasses);

        return array_values(array_unique($enumClasses));
    }

    /**
     * @param list<ImportedType> $importedTypes
     * @param array<string, list<string>> $imports
     */
    private function appendImportedTypes(string $domain, array $importedTypes, array &$imports): void
    {
        foreach ($importedTypes as $importedType) {
            if ($importedType->targetDomain === $domain) {
                continue;
            }

            $imports[$importedType->targetDomain][] = $importedType->targetTypeName;
        }
    }

    /**
     * @param array<string, list<string>> $imports
     */
    private function appendInputReferenceImport(string $domain, CollectedInputReference $input, array &$imports): void
    {
        if ($input->domain === $domain) {
            return;
        }

        $imports[$input->domain][] = $input->typeName;
    }

    /**
     * @param array<string, list<string>> $imports
     */
    private function appendExternalEnums(string $domain, ParsedType $type, array &$imports): void
    {
        if ($type instanceof ValueOfType) {
            $enumDomain = $this->enumResolver->getDomain($type->enumClass);
            if ($enumDomain !== $domain) {
                $imports[$enumDomain][] = $this->enumResolver->getShortName($type->enumClass);
            }

            return;
        }

        foreach ($this->childTypes($type) as $child) {
            $this->appendExternalEnums($domain, $child, $imports);
        }
    }

    /**
     * @param list<class-string> $enumClasses
     */
    private function appendLocalEnums(string $domain, ParsedType $type, array &$enumClasses): void
    {
        if ($type instanceof ValueOfType) {
            if ($this->enumResolver->getDomain($type->enumClass) === $domain) {
                $enumClasses[] = $this->enumResolver->resolveFqcn($type->enumClass);
            }

            return;
        }

        foreach ($this->childTypes($type) as $child) {
            $this->appendLocalEnums($domain, $child, $enumClasses);
        }
    }

    /**
     * @return list<ParsedType>
     */
    private function childTypes(ParsedType $type): array
    {
        if ($type instanceof NullableType || $type instanceof ListType) {
            return [$type->inner];
        }

        if ($type instanceof ShapeType) {
            return array_map(
                static fn(ShapeField $field): ParsedType => $field->type,
                $type->fields,
            );
        }

        if ($type instanceof UnionType) {
            return $type->types;
        }

        if ($type instanceof IntersectionType) {
            return [$type->base, $type->extra];
        }

        return [];
    }
}
