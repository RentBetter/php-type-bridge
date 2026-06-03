<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

use PTGS\TypeBridge\Config\TypeScriptNaming;
use PTGS\TypeBridge\Model\CollectedApiResponseClass;
use PTGS\TypeBridge\Model\CollectedDomain;
use PTGS\TypeBridge\Model\CollectedEndpointContract;
use PTGS\TypeBridge\Model\CollectedInputReference;
use PTGS\TypeBridge\Model\CollectedType;
use PTGS\TypeBridge\Parser\ParsedType;
use PTGS\TypeBridge\Resolver\EnumResolver;

/**
 * Per-domain rendering context handed to each {@see TypeEmitter}.
 *
 * Bridges the class-centric emitter interface back to the collected models and
 * the shared rendering services: emitters look up their model by class, render
 * types via {@see self::convert()}, resolve cross-domain references through the
 * symbol map + this domain's foreign-alias table, and never touch file assembly.
 */
final readonly class EmitContext
{
    /**
     * @param list<CollectedApiResponseClass>       $responses
     * @param list<CollectedEndpointContract>       $contracts
     * @param array<string, array<string, string>>  $foreignAliases foreignDomain => (canonicalName => aliasInThisFile)
     * @param array<string, true>                   $preserveNullIndex keyed by "ShapeName.fieldName"
     * @param list<string>                          $candidateClasses every class in the generation run (for emitters that scan, e.g. a common-module emitter)
     */
    public function __construct(
        public string $domain,
        public CollectedDomain $collected,
        public array $responses,
        public array $contracts,
        public array $foreignAliases,
        public SymbolRegistry $symbols,
        public TypeToTsConverter $converter,
        public EmittedNames $names,
        public EnumResolver $enumResolver,
        public TypeScriptNaming $naming,
        private array $preserveNullIndex,
        public array $candidateClasses = [],
    ) {}

    public function convert(ParsedType $type, ConversionScope $scope): string
    {
        return $this->converter->convert($type, $scope);
    }

    /**
     * Builds the conversion scope for a declaration, mapping each of its imported
     * type names to the local symbol (alias-aware) it resolves to in this file.
     *
     * @param list<\PTGS\TypeBridge\Model\ImportedType> $imports
     */
    public function scopeFor(array $imports): ConversionScope
    {
        $symbols = [];
        foreach ($imports as $importedType) {
            $symbols[$importedType->targetTypeName] = $this->localReferenceFor($importedType->targetDomain, $importedType->targetTypeName);
        }

        return new ConversionScope($this->domain, $symbols);
    }

    public function localReferenceFor(string $foreignDomain, string $name): string
    {
        if ($foreignDomain === $this->domain) {
            return $this->symbols->resolve($foreignDomain, $name);
        }

        return $this->foreignAliases[$foreignDomain][$name] ?? $this->symbols->resolve($foreignDomain, $name);
    }

    public function symbolForInputReference(CollectedInputReference $input): string
    {
        return $this->localReferenceFor($input->domain, $input->typeName);
    }

    public function responseSymbolName(CollectedApiResponseClass $response): string
    {
        return $this->localReferenceFor($response->domain, $response->name);
    }

    public function isPreserveNull(string $shapeName, string $fieldName): bool
    {
        return isset($this->preserveNullIndex[$shapeName . '.' . $fieldName]);
    }

    /**
     * @param class-string $class
     * @return list<CollectedType>
     */
    public function collectedTypesFor(string $class): array
    {
        return array_values(array_filter(
            $this->collected->types,
            static fn(CollectedType $type): bool => $type->ownerClass === $class,
        ));
    }

    /**
     * @param class-string $class
     */
    public function responseFor(string $class): ?CollectedApiResponseClass
    {
        foreach ($this->responses as $response) {
            if ($response->className === $class) {
                return $response;
            }
        }

        return null;
    }

    /**
     * @param class-string $class
     * @return list<CollectedEndpointContract>
     */
    public function contractsForController(string $class): array
    {
        return array_values(array_filter(
            $this->contracts,
            static fn(CollectedEndpointContract $contract): bool => $contract->controllerClass === $class,
        ));
    }
}
