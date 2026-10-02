<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

use PTGS\TypeBridge\Config\IncludeConvention;
use PTGS\TypeBridge\Config\TypeScriptNaming;
use PTGS\TypeBridge\Emitter\Builtin\EndpointContractEmitter;
use PTGS\TypeBridge\Model\CollectedApiResponseClass;
use PTGS\TypeBridge\Model\CollectedDomain;
use PTGS\TypeBridge\Model\CollectedEndpointContract;
use PTGS\TypeBridge\Model\CollectedInputReference;
use PTGS\TypeBridge\Model\CollectedResponseProperty;
use PTGS\TypeBridge\Model\ImportedType;
use PTGS\TypeBridge\Parser\GenericType;
use PTGS\TypeBridge\Parser\IdOfType;
use PTGS\TypeBridge\Parser\IntersectionType;
use PTGS\TypeBridge\Parser\ListType;
use PTGS\TypeBridge\Parser\MapType;
use PTGS\TypeBridge\Parser\NameRefType;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\ParsedType;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Parser\ShapeType;
use PTGS\TypeBridge\Parser\TupleType;
use PTGS\TypeBridge\Parser\UnionType;
use PTGS\TypeBridge\Parser\ValueOfType;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Sorting\AlphabeticalOrder;
use PTGS\TypeBridge\Sorting\SortStrategy;
use PTGS\TypeBridge\Support\DomainMapper;
use PTGS\TypeBridge\Support\RootSources;
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
    /** Names the union of a shape's or response's keys that are present whenever they are asked for. */
    public const string INCLUDED_SUFFIX = 'Included';

    /**
     * `WithIncludes<T, P>`: a type as sent when the request included paths P — one dotted path
     * (`'checks.debug'`) or a list of them — with every key they name present, at any depth. P is
     * checked against `IncludePath<T>`, the paths T has, so a misspelt one does not compile.
     */
    public const string WITH_INCLUDES_HELPER = <<<'TS'
        /** The dotted paths `?include=` can name on T, five levels deep: each key, and the paths below it. */
        export type IncludePath<T, Depth extends unknown[] = []> = Depth['length'] extends 5
          ? never
          : T extends readonly (infer Item)[]
            ? IncludePath<Item, Depth>
            : T extends object
              ? { [K in keyof T & string]-?: K | `${K}.${IncludePath<NonNullable<T[K]>, [...Depth, unknown]>}` }[keyof T & string]
              : never;

        /** T as sent when the request included paths P (`'checks.debug'`, or a list of them): every key they name is there. */
        export type WithIncludes<T, P extends IncludePath<T> | readonly IncludePath<T>[]> = WithIncludesAt<
          T,
          P extends readonly string[] ? P[number] : P
        >;

        type WithIncludesAt<T, P extends string> = [P] extends [never]
          ? T
          : T extends readonly (infer Item)[]
            ? Array<WithIncludesAt<Item, P>>
            : T extends object
              ? Omit<T, IncludeRoot<P>> & {
                  [K in IncludeRoot<P> & keyof T]-?: WithIncludesAt<Exclude<T[K], undefined>, IncludeBelow<P, K & string>>;
                }
              : T;

        type IncludeRoot<P extends string> = P extends `${infer Root}.${string}` ? Root : P;

        type IncludeBelow<P extends string, K extends string> = P extends `${K}.${infer Rest}` ? Rest : never;
        TS;

    /** The generic a `ref<T>` field emits as. */
    public const string REF_TYPE = 'Ref';

    /**
     * `Ref<T>`, and `WithExpands<T, P>`: a type as sent when the request expanded paths P, with
     * each reference they name swapped for the record it points at. P is checked against
     * `ExpandPath<T>` — the references T has, and those inside what they expand to — so a path
     * that names anything but a reference does not compile.
     *
     * `Ref<T>` is the record's id — typed as the record types its own `id` — flavoured with the
     * record under a `~ref` key that is never sent: a plain id is still assignable to it, and it
     * reads as one. A `~` key sorts last in completion lists, which is why Standard Schema marks its
     * own `~standard` the same way.
     */
    public const string WITH_EXPANDS_HELPER = <<<'TS'
        /** A related record's id. Expanding its path (`?expand=`) sends the record itself, T, instead. */
        export type Ref<T> = (T extends { id: infer Id } ? Id : string) & { readonly '~ref'?: T };

        /** The dotted paths `?expand=` can name on T: each reference, and the references inside what it expands to or sits beside. */
        export type ExpandPath<T, Depth extends unknown[] = []> = Depth['length'] extends 5
          ? never
          : T extends readonly (infer Item)[]
            ? ExpandPath<Item, Depth>
            : T extends object
              ? {
                  [K in keyof T & string]-?: [RefTarget<ListItem<NonNullable<T[K]>>>] extends [never]
                    ? NonNullable<T[K]> extends object
                      ? `${K}.${ExpandPath<NonNullable<T[K]>, [...Depth, unknown]>}`
                      : never
                    : K | `${K}.${ExpandPath<RefTarget<ListItem<NonNullable<T[K]>>>, [...Depth, unknown]>}`;
                }[keyof T & string]
              : never;

        /** T as sent when the request expanded paths P (`'checks.definition'`, or a list of them): each reference they name is its record. */
        export type WithExpands<T, P extends ExpandPath<T> | readonly ExpandPath<T>[]> = WithExpandsAt<
          T,
          P extends readonly string[] ? P[number] : P
        >;

        type WithExpandsAt<T, P extends string> = [P] extends [never]
          ? T
          : T extends readonly (infer Item)[]
            ? Array<WithExpandsAt<Item, P>>
            : T extends object
              ? { [K in keyof T]: K extends ExpandRoot<P> ? ExpandedValue<T[K], ExpandBelow<P, K & string>> : T[K] }
              : T;

        type ExpandedValue<V, P extends string> = V extends undefined
          ? undefined
          : V extends readonly (infer Item)[]
            ? Array<ExpandedValue<Item, P>>
            : [RefTarget<V>] extends [never]
              ? WithExpandsAt<V, P>
              : WithExpandsAt<RefTarget<V>, P>;

        type RefTarget<V> = V extends unknown ? ('~ref' extends keyof V ? NonNullable<V['~ref' & keyof V]> : never) : never;

        type ListItem<V> = V extends readonly (infer Item)[] ? Item : V;

        type ExpandRoot<P extends string> = P extends `${infer Root}.${string}` ? Root : P;

        type ExpandBelow<P extends string, K extends string> = P extends `${K}.${infer Rest}` ? Rest : never;
        TS;

    private EmittedNames $names;

    private SymbolRegistry $symbols;

    private TypeToTsConverter $converter;

    private ?ClassTypeResolver $classTypes = null;

    /**
     * What emit() declares in the shared root module — the config type aliases and the
     * `WithIncludes` helper the domain modules use — so emitDiscovered(), which writes that module
     * too, declares them alongside its own.
     *
     * @var array<string, EmittedBlock> keyed by the name each declares
     */
    private array $rootBlocks = [];

    /**
     * What emit() wrote into each domain's module, so emitDiscovered() can add its own
     * declarations to a module both passes write rather than replace it.
     *
     * @var array<string, array{imports: array<string, list<string>>, foreignAliases: array<string, array<string, string>>, blocks: list<EmittedBlock>}>
     */
    private array $emittedModules = [];

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
     * @param array<string, string> $typeAliases project-wide aliases, name => TypeScript type
     *   (e.g. `UuidStr` => `string`). Declared once in config rather than on a class, so a
     *   shape can name a primitive without every file importing it. Emitted into each domain
     *   that references one, and registered in that domain's symbol map so a class-declared
     *   `@phpstan-type` of the same name collides loudly instead of shadowing it.
     * @param IncludeConvention $includes what marks the parts of a response sent only when asked for
     * @param RootSources $rootSources re-homes what a discovered emitter places itself, by the rule the collectors place by
     */
    public function __construct(
        private readonly EnumResolver $enumResolver,
        private readonly DomainMapper $domainMapper,
        ?TypeScriptNaming $naming = null,
        array $preserveNull = [],
        ?EmitterRegistry $registry = null,
        ?DomainAssembler $assembler = null,
        private readonly SortStrategy $importSort = new AlphabeticalOrder(),
        private readonly array $typeAliases = [],
        private readonly IncludeConvention $includes = new IncludeConvention(),
        private readonly RootSources $rootSources = new RootSources(),
    ) {
        $this->naming = $naming ?? new TypeScriptNaming();
        $this->preserveNullIndex = array_fill_keys($preserveNull, true);
        $this->registry = $registry ?? EmitterRegistry::default();
        $this->assembler = $assembler ?? new DomainAssembler();
    }

    /**
     * @param list<string> $names
     * @return list<string>
     */
    private function orderImports(array $names): array
    {
        return $this->importSort->sort($names, static fn (string $name): string => $name);
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
        $this->classTypes = new ClassTypeResolver($domains, $this->names, $this->registry, $this->rootSources);
        $this->converter = new TypeToTsConverter($this->names, $this->symbols, $this->enumIds(), $this->classTypes, $this->includes);

        $allDomains = array_unique(array_merge(array_keys($domains), array_keys($responses), array_keys($contracts)));
        sort($allDomains);

        $rootBlocks = [];
        $output = [];
        foreach ($allDomains as $domain) {
            $output[$domain] = $this->emitDomain(
                $domain,
                $domains[$domain] ?? new CollectedDomain($domain),
                $responses[$domain] ?? [],
                $contracts[$domain] ?? [],
                $rootBlocks,
            );
        }

        // The root domain's own types — those under a root source — share the root module with
        // what every module shares, so it is written once with both.
        $this->rootBlocks = $rootBlocks;
        $root = \in_array('', $allDomains, true) ? $this->emittedModules[''] : null;
        if (null !== $root || [] !== $rootBlocks) {
            $output[''] = $this->assembler->assemble(
                null === $root ? [] : $this->renderImportLines('', $root['imports'], $root['foreignAliases']),
                [...($root['blocks'] ?? []), ...array_values($rootBlocks)],
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
        usort($discovered, static fn (RegisteredEmitter $a, RegisteredEmitter $b): int => $b->priority <=> $a->priority);

        $this->names = new EmittedNames($this->naming, $this->enumResolver);
        $this->symbols = new SymbolRegistry([]);
        $this->converter = new TypeToTsConverter($this->names, $this->symbols, $this->enumIds());

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
            candidateClasses: $classes,
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

            foreach ($discovered as $registered) {
                if (!$registered->emitter->claims($reflection)) {
                    continue;
                }

                $claimedByConvention[$registered->convention][] = $reflection;
                $this->collectEmitted($this->rootSources->placeEmitted($registered->emitter->emit($reflection, $context), $reflection), $blocksByDomain, $importsByDomain);

                break;
            }
        }

        foreach ($discovered as $registered) {
            if ($registered->emitter instanceof CommonModuleEmitter) {
                $claimed = $claimedByConvention[$registered->convention] ?? [];
                $this->collectEmitted($registered->emitter->emitCommon($claimed, $context), $blocksByDomain, $importsByDomain);
            }
        }

        // The root module is written by both passes: declare emit()'s shared blocks here too, or
        // this module would replace the one emit() returned and drop them.
        if ([] !== $this->rootBlocks) {
            $blocksByDomain[''] = [...($blocksByDomain[''] ?? []), ...array_values($this->rootBlocks)];
        }

        $output = [];
        $domains = array_keys($blocksByDomain);
        sort($domains);
        foreach ($domains as $domain) {
            // A module emit() also wrote — a subdomain holding both shapes and the enums a
            // discovered emitter claims — is written once, with both passes' declarations.
            $emitted = $this->emittedModules[$domain] ?? null;
            if (null !== $emitted) {
                $imports = $emitted['imports'];
                foreach ($importsByDomain[$domain] ?? [] as $import) {
                    if ($import->targetDomain !== $domain) {
                        $imports[$import->targetDomain][] = $import->canonicalName;
                    }
                }
                $output[$domain] = $this->assembler->assemble(
                    $this->renderImportLines($domain, $imports, $emitted['foreignAliases']),
                    [...$emitted['blocks'], ...$blocksByDomain[$domain]],
                );

                continue;
            }

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
            $lines[] = \sprintf("import type { %s } from '%s';", implode(', ', $this->orderImports(array_keys($names))), $path);
        }

        return $lines;
    }

    /**
     * @param list<CollectedApiResponseClass> $responses
     * @param list<CollectedEndpointContract> $contracts
     * @param array<string, EmittedBlock> $rootBlocks what this module shares, gathered for the root module when there is one
     */
    private function emitDomain(
        string $domain,
        CollectedDomain $collected,
        array $responses,
        array $contracts,
        array &$rootBlocks,
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
            includes: $this->includes,
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

        // Helpers every module with endpoints shares, declared once like the aliases.
        $helpers = [];
        if ([] !== $contracts) {
            if (EndpointContractEmitter::anyTakesIncludeQuery($contracts, $this->includes)) {
                $helpers[] = new EmittedBlock(40, '// Endpoint inputs', EndpointContractEmitter::includeQueryHelper($this->includes), 'IncludeQuery');
            }
            $helpers[] = new EmittedBlock(50, '// Endpoint results', EndpointContractEmitter::RESULT_HELPER, 'EndpointResult');
            $helpers[] = new EmittedBlock(60, '// Endpoints', EndpointContractEmitter::ENDPOINT_HELPER, 'Endpoint');

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

        $shared = [...$this->typeAliasBlocks($blocks), ...$this->withIncludesBlocks($blocks), ...$this->withExpandsBlocks($blocks), ...$helpers];
        if ($this->domainMapper->hasRootModule()) {
            // Declared once, in the root module; what this module uses — the aliases, `EndpointResult`
            // — is imported from it. The `WithIncludes` helper is only declared: consumers import it.
            foreach ($shared as $block) {
                $rootBlocks[$block->sortKey ?? $block->code] = $block;
                if ('' !== $domain && 'WithIncludes' !== $block->sortKey && null !== $block->sortKey) {
                    $imports[''][] = $block->sortKey;
                }
            }
        } else {
            $blocks = [...$shared, ...$blocks];
        }

        $this->emittedModules[$domain] = ['imports' => $imports, 'foreignAliases' => $foreignAliases, 'blocks' => $blocks];

        return $this->assembler->assemble($this->renderImportLines($domain, $imports, $foreignAliases), $blocks);
    }

    /**
     * `WithIncludes<T, P>` for a module that declares an `…Included` union: the type with the keys
     * a request's include paths name present, at any depth. Declared per module, like the type
     * aliases, so each module stays self-contained; the copies are identical.
     *
     * @param list<EmittedBlock> $blocks
     * @return list<EmittedBlock>
     */
    private function withIncludesBlocks(array $blocks): array
    {
        foreach ($blocks as $block) {
            if (1 === preg_match('/^export type \w+' . self::INCLUDED_SUFFIX . ' = /m', $block->code)) {
                return [new EmittedBlock(15, '// Includes', self::WITH_INCLUDES_HELPER, 'WithIncludes')];
            }
        }

        return [];
    }

    /**
     * `Ref<T>` and `WithExpands<T, P>` for a module with a `ref<T>` field. Keyed by `Ref`, the one
     * name the module itself mentions, so with a root module that is what it imports; consumers
     * import `WithExpands` themselves.
     *
     * @param list<EmittedBlock> $blocks
     * @return list<EmittedBlock>
     */
    private function withExpandsBlocks(array $blocks): array
    {
        foreach ($blocks as $block) {
            if (1 === preg_match('/\b' . self::REF_TYPE . '</', $block->code)) {
                return [new EmittedBlock(16, '// Expands', self::WITH_EXPANDS_HELPER, self::REF_TYPE)];
            }
        }

        return [];
    }

    /**
     * Declarations for the config type aliases this domain's code actually mentions.
     *
     * Emitted per domain rather than into a shared module so each domain's file stays
     * self-contained under the default relative-sibling import strategy. Two domains
     * declaring `UuidStr = string` are structurally identical in TypeScript, so values
     * still cross module boundaries freely.
     *
     * @param list<EmittedBlock> $blocks
     * @return list<EmittedBlock>
     */
    private function typeAliasBlocks(array $blocks): array
    {
        if ([] === $this->typeAliases) {
            return [];
        }

        $code = implode("\n", array_map(static fn (EmittedBlock $block): string => $block->code, $blocks));

        $aliasBlocks = [];
        foreach ($this->typeAliases as $aliasName => $tsType) {
            if (1 !== preg_match('/\\b' . preg_quote($aliasName, '/') . '\\b/', $code)) {
                continue;
            }

            $aliasBlocks[] = new EmittedBlock(10, '// Aliases', \sprintf('export type %s = %s;', $aliasName, $tsType), $aliasName);
        }

        return $aliasBlocks;
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
            $symbols = $this->orderImports(array_values(array_unique($symbols)));
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
            $this->appendImportedTypes($domain, $this->referenced($type->imports, [$type->parsed]), $imports);
            $this->appendExternalEnums($domain, $type->parsed, $imports);
            $this->appendClassTypes($domain, $type->parsed, $imports);
        }

        foreach ($responses as $response) {
            $this->appendImportedTypes($domain, $this->referenced($response->imports, array_map(
                static fn(CollectedResponseProperty $property): ParsedType => $property->parsed,
                $response->properties,
            )), $imports);
            foreach ($response->properties as $property) {
                $this->appendExternalEnums($domain, $property->parsed, $imports);
                $this->appendClassTypes($domain, $property->parsed, $imports);
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
     * The imports a type actually uses. A class's `@phpstan-import-type` tags serve every alias it
     * declares and its own code, so one of them may be used by none of the shapes emitted here —
     * and an import nothing references fails the consumer's unused-import check.
     *
     * @param list<ImportedType> $importedTypes
     * @param list<ParsedType> $types
     * @return list<ImportedType>
     */
    private function referenced(array $importedTypes, array $types): array
    {
        $names = [];
        $collect = function (ParsedType $type) use (&$collect, &$names): void {
            if ($type instanceof NameRefType) {
                $names[$type->name] = true;
            }
            foreach ($this->childTypes($type) as $child) {
                $collect($child);
            }
        };
        foreach ($types as $type) {
            $collect($type);
        }

        return array_values(array_filter(
            $importedTypes,
            static fn(ImportedType $imported): bool => isset($names[$imported->targetTypeName]),
        ));
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

    private function enumIds(): EnumIdSymbolResolver
    {
        return new EnumIdSymbolResolver($this->enumResolver, $this->registry, $this->rootSources);
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

        // The id union is emitted by whichever emitter publishes it — usually in another
        // pass and so another module — so this only ever needs the import, never a local
        // declaration. appendLocalEnums has no id-of arm for that reason.
        if ($type instanceof IdOfType) {
            $symbol = $this->enumIds()->resolve($type->enumClass);
            if ($symbol->targetDomain !== $domain) {
                $imports[$symbol->targetDomain][] = $symbol->canonicalName;
            }

            return;
        }

        foreach ($this->childTypes($type) as $child) {
            $this->appendExternalEnums($domain, $child, $imports);
        }
    }

    /**
     * Imports for the classes a type names where no alias answers to the name — see
     * {@see ClassTypeResolver}. The same test the converter makes, so what is imported is what
     * is referenced.
     *
     * @param array<string, list<string>> $imports
     */
    private function appendClassTypes(string $domain, ParsedType $type, array &$imports): void
    {
        if ($type instanceof NameRefType) {
            if (null !== $type->class && null !== $this->classTypes && !$this->symbols->has($domain, $type->name)) {
                $symbol = $this->classTypes->resolve($type->class);
                if ($symbol->targetDomain !== $domain) {
                    $imports[$symbol->targetDomain][] = $symbol->canonicalName;
                }
            }

            return;
        }

        foreach ($this->childTypes($type) as $child) {
            $this->appendClassTypes($domain, $child, $imports);
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

        // Both sides: a nested shape or a value-of enum can sit in either
        // position, and skipping the key side would drop its local type from
        // the emitted file.
        if ($type instanceof MapType) {
            return [$type->key, $type->value];
        }

        if ($type instanceof TupleType) {
            return $type->elements;
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

        if ($type instanceof GenericType) {
            return $type->arguments;
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

            foreach (array_keys($this->typeAliases) as $aliasName) {
                $this->registerSymbol(
                    domain: $domain,
                    logicalName: $aliasName,
                    emittedName: $aliasName,
                    descriptor: 'config type alias ' . $aliasName,
                    maps: $maps,
                    registrations: $registrations,
                );
            }

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
