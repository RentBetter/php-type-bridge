<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

use PTGS\TypeBridge\Config\TypeScriptNaming;
use PTGS\TypeBridge\Emitter\Builtin\EndpointContractEmitter;
use PTGS\TypeBridge\Model\CollectedApiResponseClass;
use PTGS\TypeBridge\Model\CollectedDomain;
use PTGS\TypeBridge\Model\CollectedEndpointContract;
use PTGS\TypeBridge\Model\CollectedInputReference;
use PTGS\TypeBridge\Model\ImportedType;
use PTGS\TypeBridge\Parser\IntersectionType;
use PTGS\TypeBridge\Parser\ListType;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\ParsedType;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Parser\UnionType;
use PTGS\TypeBridge\Parser\ValueOfType;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Support\DomainMapper;
use ReflectionClass;
use RuntimeException;

/**
 * Orchestrates TypeScript generation: builds the shared services and per-domain
 * context, routes each domain's classes to their owning {@see TypeEmitter}, and
 * hands the emitted blocks to {@see DomainAssembler}.
 *
 * Cross-domain import collection and collision-avoiding aliasing live here (they
 * span every declaration in a domain); per-declaration rendering lives in the
 * emitters.
 */
final class TypeScriptEmitter
{
    private EmittedNames $names;

    private SymbolRegistry $symbols;

    private TypeToTsConverter $converter;

    private TypeScriptNaming $naming;

    private readonly EmitterRegistry $registry;

    private readonly DomainAssembler $assembler;

    /** @var array<string, true> indexed by "ShapeName.fieldName" for O(1) lookup */
    private array $preserveNullIndex;

    /**
     * @param list<string> $preserveNull entries of the form "ShapeName.fieldName".
     *   Fields listed here must use `T|null` in their @phpstan-type annotation
     *   and emit as `field: T | null`. All other nullable fields must use `?T`
     *   and emit as `field?: T`. A mismatch raises RuntimeException at emit time.
     */
    public function __construct(
        private readonly EnumResolver $enumResolver,
        private readonly DomainMapper $domainMapper,
        ?TypeScriptNaming $naming = null,
        array $preserveNull = [],
        ?EmitterRegistry $registry = null,
        ?DomainAssembler $assembler = null,
    ) {
        $this->naming = $naming ?? new TypeScriptNaming();
        $this->preserveNullIndex = array_fill_keys($preserveNull, true);
        $this->registry = $registry ?? EmitterRegistry::default();
        $this->assembler = $assembler ?? new DomainAssembler();
    }

    /**
     * @param array<string, CollectedDomain> $domains
     * @param array<string, list<CollectedApiResponseClass>> $responses
     * @param array<string, list<CollectedEndpointContract>> $contracts
     * @return array<string, string>
     */
    public function emit(array $domains, array $responses = [], array $contracts = []): array
    {
        $this->names = new EmittedNames($this->naming, $this->enumResolver);
        $this->symbols = new SymbolRegistry($this->buildSymbolMaps($domains, $responses));
        $this->converter = new TypeToTsConverter($this->names, $this->symbols);

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
     * Generates modules from Discovered-mode emitters (and their optional common
     * module), routing each candidate class to its owning emitter. Kept separate
     * from {@see self::emit()} so the built-in Referenced conventions are unaffected.
     *
     * @param list<string> $classes candidate classes to scan
     * @return array<string, string> domain (or '' for the root module) => TypeScript
     */
    public function emitDiscovered(array $classes): array
    {
        $discovered = $this->registry->discovered();
        if ([] === $discovered) {
            return [];
        }

        $this->names = new EmittedNames($this->naming, $this->enumResolver);
        $this->symbols = new SymbolRegistry([]);
        $this->converter = new TypeToTsConverter($this->names, $this->symbols);

        $context = new EmitContext(
            domain: '',
            collected: new CollectedDomain(''),
            responses: [],
            contracts: [],
            foreignAliases: [],
            symbols: $this->symbols,
            converter: $this->converter,
            names: $this->names,
            enumResolver: $this->enumResolver,
            naming: $this->naming,
            preserveNullIndex: $this->preserveNullIndex,
        );

        /** @var array<string, list<EmittedBlock>> $blocksByDomain */
        $blocksByDomain = [];
        /** @var array<string, list<EmitImport>> $importsByDomain */
        $importsByDomain = [];
        /** @var array<string, list<ReflectionClass<object>>> $claimedByConvention */
        $claimedByConvention = [];

        foreach ($classes as $class) {
            $reflection = $this->reflect($class);
            if (null === $reflection) {
                continue;
            }

            $owner = $this->registry->ownerFor($reflection);
            if (null === $owner || EmitMode::Discovered !== $owner->mode) {
                continue;
            }

            $claimedByConvention[$owner->convention][] = $reflection;
            $this->collectEmitted($owner->emitter->emit($reflection, $context), $blocksByDomain, $importsByDomain);
        }

        foreach ($discovered as $registered) {
            if ($registered->emitter instanceof CommonModuleEmitter) {
                $claimed = $claimedByConvention[$registered->convention] ?? [];
                $this->collectEmitted($registered->emitter->emitCommon($claimed, $context), $blocksByDomain, $importsByDomain);
            }
        }

        $output = [];
        $domains = array_keys($blocksByDomain);
        sort($domains);
        foreach ($domains as $domain) {
            $output[$domain] = $this->assembler->assemble(
                $this->renderEmitterImports($domain, $importsByDomain[$domain] ?? []),
                $blocksByDomain[$domain],
            );
        }

        return $output;
    }

    /**
     * @param array<string, list<EmittedBlock>> $blocksByDomain
     * @param array<string, list<EmitImport>>   $importsByDomain
     */
    private function collectEmitted(EmittedType $emitted, array &$blocksByDomain, array &$importsByDomain): void
    {
        $blocksByDomain[$emitted->domain] = array_merge($blocksByDomain[$emitted->domain] ?? [], $emitted->blocks);
        $importsByDomain[$emitted->domain] = array_merge($importsByDomain[$emitted->domain] ?? [], $emitted->imports);
    }

    /**
     * Renders emitter-declared imports, grouped by target module, preserving the
     * order the emitter declared them (the convention owns ordering).
     *
     * @param list<EmitImport> $imports
     * @return list<string>
     */
    private function renderEmitterImports(string $domain, array $imports): array
    {
        /** @var array<string, array<string, true>> $byTarget */
        $byTarget = [];
        foreach ($imports as $import) {
            if ($import->targetDomain === $domain) {
                continue;
            }
            $byTarget[$import->targetDomain][$import->canonicalName] = true;
        }

        $lines = [];
        foreach ($byTarget as $targetDomain => $names) {
            $path = '' === $targetDomain
                ? $this->domainMapper->getRootImportPath($domain)
                : $this->domainMapper->getRelativeImportPath($domain, $targetDomain);
            $lines[] = \sprintf("import type { %s } from '%s';", implode(', ', array_keys($names)), $path);
        }

        return $lines;
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
        $imports = $this->collectExternalImports($domain, $collected, $responses, $contracts);
        $foreignAliases = $this->computeForeignAliases($domain, $collected, $responses, $contracts, $imports);

        $context = new EmitContext(
            domain: $domain,
            collected: $collected,
            responses: $responses,
            contracts: $contracts,
            foreignAliases: $foreignAliases,
            symbols: $this->symbols,
            converter: $this->converter,
            names: $this->names,
            enumResolver: $this->enumResolver,
            naming: $this->naming,
            preserveNullIndex: $this->preserveNullIndex,
        );

        $blocks = [];

        foreach ($this->collectLocalEnums($domain, $collected, $responses) as $enumClass) {
            $reflection = $this->reflect($enumClass);
            if (null !== $reflection) {
                $blocks = array_merge($blocks, $this->registry->byConvention('value-of')->emit($reflection, $context)->blocks);
            }
        }

        $seenShapeOwners = [];
        foreach ($collected->types as $type) {
            if (isset($seenShapeOwners[$type->ownerClass])) {
                continue;
            }
            $seenShapeOwners[$type->ownerClass] = true;
            $reflection = $this->reflect($type->ownerClass);
            if (null !== $reflection) {
                $blocks = array_merge($blocks, $this->registry->byConvention('_self')->emit($reflection, $context)->blocks);
            }
        }

        foreach ($responses as $response) {
            $reflection = $this->reflect($response->className);
            if (null !== $reflection) {
                $blocks = array_merge($blocks, $this->registry->byConvention('responses')->emit($reflection, $context)->blocks);
            }
        }

        if ([] !== $contracts) {
            $blocks[] = new EmittedBlock(50, '// Endpoint results', EndpointContractEmitter::RESULT_HELPER);

            $seenControllers = [];
            foreach ($contracts as $contract) {
                if (isset($seenControllers[$contract->controllerClass])) {
                    continue;
                }
                $seenControllers[$contract->controllerClass] = true;
                $reflection = $this->reflect($contract->controllerClass);
                if (null !== $reflection) {
                    $blocks = array_merge($blocks, $this->registry->byConvention('endpoint-contracts')->emit($reflection, $context)->blocks);
                }
            }
        }

        return $this->assembler->assemble($this->renderImportLines($domain, $imports, $foreignAliases), $blocks);
    }

    /**
     * @param array<string, list<string>> $imports
     * @param array<string, array<string, string>> $foreignAliases
     * @return list<string>
     */
    private function renderImportLines(string $domain, array $imports, array $foreignAliases): array
    {
        $lines = [];
        foreach ($imports as $importDomain => $symbols) {
            sort($symbols);
            $symbols = array_values(array_unique($symbols));
            $tokens = array_map(static function (string $symbol) use ($importDomain, $foreignAliases): string {
                $alias = $foreignAliases[$importDomain][$symbol] ?? $symbol;

                return $alias === $symbol ? $symbol : \sprintf('%s as %s', $symbol, $alias);
            }, $symbols);
            $path = '' === $importDomain
                ? $this->domainMapper->getRootImportPath($domain)
                : $this->domainMapper->getRelativeImportPath($domain, $importDomain);
            $lines[] = \sprintf(
                "import type { %s } from '%s';",
                implode(', ', $tokens),
                $path,
            );
        }

        return $lines;
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
            foreach ($contract->responses as $response) {
                if ($response->domain === $domain) {
                    continue;
                }

                $imports[$response->domain][] = $this->symbolFor($response->domain, $response->name);
            }

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
     * @return list<string>
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

            $imports[$importedType->targetDomain][] = $this->symbolFor($importedType->targetDomain, $importedType->targetTypeName);
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

        $imports[$input->domain][] = $this->symbolFor($input->domain, $input->typeName);
    }

    /**
     * @param array<string, list<string>> $imports
     */
    private function appendExternalEnums(string $domain, ParsedType $type, array &$imports): void
    {
        if ($type instanceof ValueOfType) {
            $enumDomain = $this->enumResolver->getDomain($type->enumClass);
            if ($enumDomain !== $domain) {
                $imports[$enumDomain][] = $this->names->enumName($type->enumClass);
            }

            return;
        }

        foreach ($this->childTypes($type) as $child) {
            $this->appendExternalEnums($domain, $child, $imports);
        }
    }

    /**
     * @param list<string> $enumClasses
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

    /**
     * @param array<string, CollectedDomain> $domains
     * @param array<string, list<CollectedApiResponseClass>> $responses
     * @return array<string, array<string, string>>
     */
    private function buildSymbolMaps(array $domains, array $responses): array
    {
        $maps = [];
        $registrations = [];
        $allDomains = array_unique(array_merge(array_keys($domains), array_keys($responses)));

        foreach ($allDomains as $domain) {
            $maps[$domain] = [];
            $registrations[$domain] = [];

            foreach (($domains[$domain] ?? new CollectedDomain($domain))->types as $type) {
                $this->registerSymbol(
                    domain: $domain,
                    logicalName: $type->name,
                    emittedName: $this->names->typeDeclarationName($type),
                    descriptor: 'type ' . $type->ownerClass,
                    maps: $maps,
                    registrations: $registrations,
                );
            }

            foreach ($responses[$domain] ?? [] as $response) {
                $this->registerSymbol(
                    domain: $domain,
                    logicalName: $response->name,
                    emittedName: $this->names->responseDeclarationName($response),
                    descriptor: 'response ' . $response->className,
                    maps: $maps,
                    registrations: $registrations,
                );
            }

            foreach ($this->collectLocalEnums($domain, $domains[$domain] ?? new CollectedDomain($domain), $responses[$domain] ?? []) as $enumClass) {
                $emittedName = $this->names->enumName($enumClass);
                if (isset($registrations[$domain][$emittedName])) {
                    throw new RuntimeException(\sprintf(
                        'TypeScript naming collision in domain "%s": "%s" is emitted by both %s and enum %s.',
                        $domain,
                        $emittedName,
                        $registrations[$domain][$emittedName],
                        $enumClass,
                    ));
                }

                $registrations[$domain][$emittedName] = 'enum ' . $enumClass;
            }
        }

        return $maps;
    }

    /**
     * @param array<string, array<string, string>> $maps
     * @param array<string, array<string, string>> $registrations
     */
    private function registerSymbol(
        string $domain,
        string $logicalName,
        string $emittedName,
        string $descriptor,
        array &$maps,
        array &$registrations,
    ): void {
        if (isset($registrations[$domain][$emittedName])) {
            throw new RuntimeException(\sprintf(
                'TypeScript naming collision in domain "%s": "%s" is emitted by both %s and %s.',
                $domain,
                $emittedName,
                $registrations[$domain][$emittedName],
                $descriptor,
            ));
        }

        $maps[$domain][$logicalName] = $emittedName;
        $registrations[$domain][$emittedName] = $descriptor;
    }

    private function symbolFor(string $domain, string $logicalName): string
    {
        return $this->symbols->resolve($domain, $logicalName);
    }

    /**
     * Reflects a collected class-like name. Returns null only when the name is not
     * loadable — collected models never produce that, so the guard exists to make
     * the class-string narrowing explicit for static analysis.
     *
     * @return ReflectionClass<object>|null
     */
    private function reflect(string $className): ?ReflectionClass
    {
        if (!class_exists($className) && !interface_exists($className) && !trait_exists($className)) {
            return null;
        }

        return new ReflectionClass($className);
    }

    /**
     * @param list<CollectedApiResponseClass> $responses
     * @param list<CollectedEndpointContract> $contracts
     * @param array<string, list<string>> $imports
     * @return array<string, array<string, string>>
     */
    private function computeForeignAliases(
        string $domain,
        CollectedDomain $collected,
        array $responses,
        array $contracts,
        array $imports,
    ): array {
        $usedNames = $this->localEmittedNames($domain, $collected, $responses, $contracts);
        $aliases = [];

        $importDomains = array_keys($imports);
        sort($importDomains);

        foreach ($importDomains as $importDomain) {
            $names = array_values(array_unique($imports[$importDomain]));
            sort($names);
            foreach ($names as $name) {
                $alias = $name;
                if (isset($usedNames[$alias])) {
                    $alias = $importDomain . $name;
                    $suffix = 0;
                    while (isset($usedNames[$alias])) {
                        $alias = $importDomain . $name . (++$suffix);
                    }
                }
                $aliases[$importDomain][$name] = $alias;
                $usedNames[$alias] = true;
            }
        }

        return $aliases;
    }

    /**
     * @param list<CollectedApiResponseClass> $responses
     * @param list<CollectedEndpointContract> $contracts
     * @return array<string, true>
     */
    private function localEmittedNames(string $domain, CollectedDomain $collected, array $responses, array $contracts): array
    {
        $names = [];
        foreach ($collected->types as $type) {
            $names[$this->names->typeDeclarationName($type)] = true;
        }
        foreach ($responses as $response) {
            $names[$this->names->responseDeclarationName($response)] = true;
        }
        foreach ($this->collectLocalEnums($domain, $collected, $responses) as $enumClass) {
            $names[$this->names->enumName($enumClass)] = true;
        }
        foreach ($contracts as $contract) {
            $request = $contract->request;
            if (null !== $request) {
                if (null !== $request->query) {
                    $names[$this->naming->queryAliasName($contract->name)] = true;
                }
                if (null !== $request->body) {
                    $names[$this->naming->bodyAliasName($contract->name)] = true;
                }
                if (null !== $request->path) {
                    $names[$this->naming->pathAliasName($contract->name)] = true;
                }
            }
            $names[$this->naming->endpointMapName($contract->name)] = true;
            $names[$this->naming->endpointResultName($contract->name)] = true;
        }

        return $names;
    }
}
