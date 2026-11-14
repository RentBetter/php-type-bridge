<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

use PTGS\TypeBridge\Config\IncludeConvention;
use PTGS\TypeBridge\Config\TypeScriptNaming;
use PTGS\TypeBridge\Emitter\Builtin\EndpointContractEmitter;
use PTGS\TypeBridge\Emitter\Builtin\ResponseClassEmitter;
use PTGS\TypeBridge\Emitter\Builtin\SelfShapeEmitter;
use PTGS\TypeBridge\Model\CollectedApiResponseClass;
use PTGS\TypeBridge\Model\CollectedDomain;
use PTGS\TypeBridge\Model\CollectedEndpointContract;
use PTGS\TypeBridge\Model\CollectedInputReference;
use PTGS\TypeBridge\Model\ImportedType;
use PTGS\TypeBridge\Parser\IdOfType;
use PTGS\TypeBridge\Parser\NameRefType;
use PTGS\TypeBridge\Parser\ParsedType;
use PTGS\TypeBridge\Parser\ParsedTypes;
use PTGS\TypeBridge\Parser\ScopedType;
use PTGS\TypeBridge\Parser\ValueOfType;
use PTGS\TypeBridge\Resolver\EnumResolver;
use PTGS\TypeBridge\Sorting\AlphabeticalOrder;
use PTGS\TypeBridge\Sorting\SortStrategy;
use PTGS\TypeBridge\Support\DomainMapper;
use PTGS\TypeBridge\Support\PhpFileClassLocator;
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

    /** The global types of TypeScript's the generated code writes: a module type of one of these names would shadow it. */
    private const array TYPESCRIPT_GLOBALS = ['Array', 'Exclude', 'NonNullable', 'Omit', 'Record'];

    /** The include and expand helpers by what each is for, in the order they are declared. */
    private const array INCLUDE_HELPERS = ['including' => self::WITH_INCLUDES_HELPER, 'expanding' => self::WITH_EXPANDS_HELPER];

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

    /** @var array<string, list<string>> by module, the enums of its own anything emitted names */
    private array $enumsByDomain = [];

    /** @var array<string, array<string, string>> by module, the include and expand helpers its own types use: what each is for, and its code */
    private array $includeHelpers = [];

    /** @var array<string, array<string, string>> by module, each name it declares or imports for its own, and what claims it ({@see self::buildSymbolMaps()}) */
    private array $claimedNames = [];

    /** @var array<string, list<string>> by module, the consts its endpoints declare: values, which a type of the module may share a name with, but an import may not */
    private array $endpointConsts = [];

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
        // A map keyed by an alias keyed as the alias is: known here, where every module's aliases are.
        $keys = new AliasedMapKeys($domains);
        $domains = $keys->inDomains($domains);
        $responses = $keys->inResponses($responses);

        $this->names = new EmittedNames($this->naming, $this->enumResolver);
        $this->enumsByDomain = $this->referencedEnums($domains, $responses);
        $this->includeHelpers = $this->includeHelpersUsed($domains, $responses);

        // Every module written: one whose enum something emitted elsewhere names, if only for the enum.
        $allDomains = array_values(array_unique(array_merge(array_keys($domains), array_keys($responses), array_keys($contracts), array_keys($this->enumsByDomain))));
        sort($allDomains);

        $this->symbols = new SymbolRegistry($this->buildSymbolMaps($allDomains, $domains, $responses, $contracts), array_keys($this->typeAliases));
        $this->classTypes = new ClassTypeResolver($domains, $this->names, $this->registry, $this->rootSources, $responses);
        $this->converter = new TypeToTsConverter($this->names, $this->symbols, $this->enumIds(), $this->classTypes, $this->includes);

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
            $output[''] = $this->unshadowed('', $this->assembler->assemble(
                null === $root ? [] : $this->renderImportLines('', $root['imports'], $root['foreignAliases']),
                [...($root['blocks'] ?? []), ...array_values($rootBlocks)],
            ));
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
        $foreignAliases = $this->computeForeignAliases($domain, $imports);

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

        foreach ($this->collectLocalEnums($domain) as $enumClass) {
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
        $helpers = EndpointContractEmitter::helpers($contracts, $this->includes);
        if ([] !== $contracts) {
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

        $shared = [...$this->typeAliasBlocks($blocks), ...$this->includeHelperBlocks($domain), ...$helpers];
        if ($this->domainMapper->hasRootModule()) {
            // Declared once, in the root module; what this module uses — the aliases, `EndpointResult`
            // — is imported from it. The `WithIncludes` helper is only declared: consumers import it.
            // One named as an endpoint of the module is declared in it, as without a root module: an
            // import may not share a name with the module's `const` (TS2395), where a type it declares
            // may.
            // A config alias only another names is declared there for it, and imported only where the
            // module's own code names it too.
            $consts = array_fill_keys(array_map(static fn (CollectedEndpointContract $contract): string => $contract->name, $contracts), true);
            $own = [];
            $imported = [];
            foreach ($shared as $block) {
                $rootBlocks[$block->sortKey ?? $block->code] = $block;
                if ('' === $domain || 'WithIncludes' === $block->sortKey || null === $block->sortKey) {
                    continue;
                }
                if (isset($consts[$block->sortKey])) {
                    $own[] = $block;
                } else {
                    $imported[] = $block->sortKey;
                }
            }
            $named = $this->configAliasesNamedIn([...$own, ...$blocks]);
            foreach ($imported as $name) {
                if (!isset($this->typeAliases[$name]) || isset($named[$name])) {
                    $imports[''][] = $name;
                }
            }
            $blocks = [...$own, ...$blocks];
        } else {
            $blocks = [...$shared, ...$blocks];
        }

        $this->emittedModules[$domain] = ['imports' => $imports, 'foreignAliases' => $foreignAliases, 'blocks' => $blocks];

        return $this->unshadowed($domain, $this->assembler->assemble($this->renderImportLines($domain, $imports, $foreignAliases), $blocks));
    }

    /**
     * The module's code, where no type it declares shadows a global type of TypeScript's its code
     * uses — `Record<string, V>` beside an `interface Record` reads the module's, and fails. One it
     * imports is imported under another name ({@see self::computeForeignAliases()}).
     */
    private function unshadowed(string $domain, string $code): string
    {
        foreach (self::TYPESCRIPT_GLOBALS as $global) {
            $declared = $this->claimedNames[$domain][$global] ?? null;
            if (null !== $declared && 1 === preg_match('/\\b' . $global . '</', $code)) {
                throw new RuntimeException(\sprintf(
                    'TypeScript naming collision in domain "%s": "%s" is both TypeScript\'s global %s, which the module\'s code uses, and %s.',
                    $domain,
                    $global,
                    $global,
                    $declared,
                ));
            }
        }

        return $code;
    }

    /**
     * The include and expand helpers a module's own types use ({@see self::includeHelpersUsed()}).
     * `WithIncludes<T, P>` reads its `…Included` unions: the type with the keys a request's include
     * paths name present, at any depth. `Ref<T>` and `WithExpands<T, P>` are keyed by `Ref`, the one
     * name the module itself mentions, so with a root module that is what it imports; consumers
     * import `WithIncludes` and `WithExpands` themselves. Declared per module, like the type aliases,
     * so each module stays self-contained; the copies are identical.
     *
     * @return list<EmittedBlock>
     */
    private function includeHelperBlocks(string $domain): array
    {
        $helpers = $this->includeHelpers[$domain] ?? [];

        return [
            ...(isset($helpers['including']) ? [new EmittedBlock(15, '// Includes', self::WITH_INCLUDES_HELPER, 'WithIncludes')] : []),
            ...(isset($helpers['expanding']) ? [new EmittedBlock(16, '// Expands', self::WITH_EXPANDS_HELPER, self::REF_TYPE)] : []),
        ];
    }

    /**
     * By module, the include and expand helpers its own types use: `WithIncludes` where one declares
     * an `…Included` union — a key present whenever it is asked for — and `Ref` and `WithExpands`
     * where one holds a reference. Read off the types before any is emitted, so what a module
     * declares is what it claims the names of ({@see self::includeHelperNames()}).
     *
     * @param array<string, CollectedDomain>                 $domains
     * @param array<string, list<CollectedApiResponseClass>> $responses
     *
     * @return array<string, array<string, string>>
     */
    private function includeHelpersUsed(array $domains, array $responses): array
    {
        $used = [];
        foreach ($domains as $domain => $collected) {
            foreach ($collected->types as $type) {
                if ([] !== SelfShapeEmitter::includedKeys($type, $this->includes)) {
                    $used[$domain]['including'] = true;
                }
                if ($this->includes->hasRef($type->parsed)) {
                    $used[$domain]['expanding'] = true;
                }
            }
        }
        foreach ($responses as $domain => $domainResponses) {
            foreach ($domainResponses as $response) {
                if ([] !== ResponseClassEmitter::includedProperties($response, $this->includes)) {
                    $used[$domain]['including'] = true;
                }
                foreach ($response->properties as $property) {
                    if ($this->includes->hasRef($property->parsed)) {
                        $used[$domain]['expanding'] = true;
                    }
                }
            }
        }

        return array_map(static fn (array $kinds): array => array_intersect_key(self::INCLUDE_HELPERS, $kinds), $used);
    }

    /**
     * Declarations for the config type aliases this domain's code actually mentions, and those they
     * name in turn (`NoteId: 'UuidStr'`), in the order the config lists them.
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
        $named = $this->configAliasesNamedIn($blocks);
        for ($pending = array_keys($named); [] !== $pending;) {
            $name = array_pop($pending);
            foreach (array_keys($this->configAliasesNamedIn([new EmittedBlock(10, '', $this->typeAliases[$name])])) as $next) {
                if (!isset($named[$next])) {
                    $named[$next] = true;
                    $pending[] = $next;
                }
            }
        }

        $aliasBlocks = [];
        foreach ($this->typeAliases as $aliasName => $tsType) {
            if (isset($named[$aliasName])) {
                $aliasBlocks[] = new EmittedBlock(10, '// Aliases', \sprintf('export type %s = %s;', $aliasName, $tsType), $aliasName);
            }
        }

        return $aliasBlocks;
    }

    /**
     * The config type aliases the blocks' code names.
     *
     * @param list<EmittedBlock> $blocks
     * @return array<string, true>
     */
    private function configAliasesNamedIn(array $blocks): array
    {
        $code = implode("\n", array_map(static fn (EmittedBlock $block): string => $block->code, $blocks));

        $named = [];
        foreach (array_keys($this->typeAliases) as $aliasName) {
            if (1 === preg_match('/\\b' . preg_quote($aliasName, '/') . '\\b/', $code)) {
                $named[$aliasName] = true;
            }
        }

        return $named;
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
            $this->appendImportedTypes($domain, ImportedType::usedIn($type->imports, $type->parsed), $imports);
            $this->appendExternalEnums($domain, $type->parsed, $imports);
            $this->appendClassTypes($domain, $type->parsed, $imports, $type->imports);
        }

        foreach ($responses as $response) {
            foreach ($response->properties as $property) {
                // Each property imports what the file it is written in imports, and the aliases of
                // that file's module it names bare.
                $this->appendImportedTypes($domain, ImportedType::usedIn($property->imports, $property->parsed), $imports);
                $this->appendExternalEnums($domain, $property->parsed, $imports);
                $this->appendClassTypes($domain, $property->parsed, $imports, $property->imports, $property->writtenInDomain);
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
     * The enums a module emits: those of its own that anything emitted names, in any module — a
     * response in another module inheriting a property typed with one included.
     *
     * @return list<string>
     */
    private function collectLocalEnums(string $domain): array
    {
        return $this->enumsByDomain[$domain] ?? [];
    }

    /**
     * Every enum the types and responses name, each under the module it is declared in.
     *
     * @param array<string, CollectedDomain>                 $domains
     * @param array<string, list<CollectedApiResponseClass>> $responses
     *
     * @return array<string, list<string>>
     */
    private function referencedEnums(array $domains, array $responses): array
    {
        $types = [];
        foreach ($domains as $collected) {
            foreach ($collected->types as $type) {
                $types[] = $type->parsed;
            }
        }
        foreach ($responses as $domainResponses) {
            foreach ($domainResponses as $response) {
                foreach ($response->properties as $property) {
                    $types[] = $property->parsed;
                }
            }
        }

        $enums = [];
        while ([] !== $types) {
            $type = array_pop($types);
            if ($type instanceof ValueOfType) {
                $enum = $this->enumResolver->resolveFqcn($type->enumClass);
                $enums[$this->enumResolver->getDomain($enum)][$enum] = $enum;

                continue;
            }
            array_push($types, ...ParsedTypes::children($type));
        }

        $byDomain = [];
        foreach ($enums as $domain => $classes) {
            sort($classes);
            $byDomain[$domain] = $classes;
        }

        return $byDomain;
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

        foreach (ParsedTypes::children($type) as $child) {
            $this->appendExternalEnums($domain, $child, $imports);
        }
    }

    /**
     * Imports for the names a type uses that no import of its file answers to: what each refers to
     * as the converter reads it ({@see TypeToTsConverter::referenceOf()}) — a class's type, an alias of
     * the module it is written in, a type another file writes ({@see ScopedType}) — from the module
     * that declares it, where that is not this one. One choice, so what is imported is what is
     * referenced.
     *
     * @param array<string, list<string>> $imports
     * @param list<ImportedType>          $fileImports the imports of the file the type is written in
     * @param string|null                 $aliasDomain the module the type is written in, where it is not this one
     */
    private function appendClassTypes(string $domain, ParsedType $type, array &$imports, array $fileImports = [], ?string $aliasDomain = null): void
    {
        $this->appendReferences($domain, $type, $imports, new ConversionScope(
            $domain,
            array_fill_keys(array_map(static fn(ImportedType $import): string => $import->localAlias, $fileImports), ''),
            [],
            null === $aliasDomain || $aliasDomain === $domain ? null : $aliasDomain,
        ));
    }

    /**
     * @param array<string, list<string>> $imports
     */
    private function appendReferences(string $domain, ParsedType $type, array &$imports, ConversionScope $scope): void
    {
        if ($type instanceof ScopedType) {
            $this->appendImportedTypes($domain, ImportedType::usedIn($type->imports, $type->inner), $imports);
            $this->appendReferences($domain, $type->inner, $imports, $this->converter->scopeOf($type, $scope));

            return;
        }
        if ($type instanceof NameRefType) {
            [$target, $symbol] = $this->converter->referenceOf($type, $scope);
            if (null !== $target && $target !== $domain) {
                $imports[$target][] = $symbol;
            }

            return;
        }

        foreach (ParsedTypes::children($type) as $child) {
            $this->appendReferences($domain, $child, $imports, $scope);
        }
    }

    /**
     * @param list<string> $modules every module written ({@see self::emit()}), one written only for an
     *        enum named from elsewhere included: its names collide as any other's
     * @param array<string, CollectedDomain> $domains
     * @param array<string, list<CollectedApiResponseClass>> $responses
     * @param array<string, list<CollectedEndpointContract>> $contracts
     * @return array<string, array<string, string>>
     */
    private function buildSymbolMaps(array $modules, array $domains, array $responses, array $contracts): array
    {
        $maps = [];
        $registrations = [];
        // The root module, where one is set, declares what every module shares, whether it has types
        // of its own or not.
        $allDomains = $this->domainMapper->hasRootModule() ? array_unique([...$modules, '']) : $modules;

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

            $endpoints = array_map(static fn (CollectedEndpointContract $contract): string => $contract->name, $contracts[$domain] ?? []);
            foreach ($this->includeHelperNames($domain, $endpoints) as $name => $what) {
                $this->registerSymbol(domain: $domain, logicalName: null, emittedName: $name, descriptor: $what, maps: $maps, registrations: $registrations);
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
                // Declared beside it, though nothing is typed by it: no other type may take it.
                $was = $this->names->deprecatedResponseName($response);
                if (null !== $was) {
                    $this->registerSymbol(
                        domain: $domain,
                        logicalName: null,
                        emittedName: $was,
                        descriptor: 'the deprecated name of response ' . $response->className,
                        maps: $maps,
                        registrations: $registrations,
                    );
                }
            }

            foreach ($this->collectLocalEnums($domain) as $enumClass) {
                $this->registerSymbol(
                    domain: $domain,
                    logicalName: null,
                    emittedName: $this->names->enumName($enumClass),
                    descriptor: 'enum ' . $enumClass,
                    maps: $maps,
                    registrations: $registrations,
                );
            }

            // What a module's endpoints declare — the helpers they share, declared here or imported from
            // the root module, and each one's inputs, map and result — no other type of it may be named,
            // nor any of them as another.
            $domainContracts = $contracts[$domain] ?? [];
            foreach (EndpointContractEmitter::helperNames($domainContracts, $this->includes) as $name) {
                $this->registerSymbol(domain: $domain, logicalName: null, emittedName: $name, descriptor: 'the endpoint helper ' . $name, maps: $maps, registrations: $registrations);
            }
            foreach ($domainContracts as $contract) {
                foreach (EndpointContractEmitter::endpointNames($contract, $this->naming, $this->includes) as [$name, $what]) {
                    $this->registerSymbol(domain: $domain, logicalName: null, emittedName: $name, descriptor: $what, maps: $maps, registrations: $registrations);
                }
            }
        }

        // The helpers are declared once, in the root module, where one is set: none of its own types
        // may be named as one either, nor its own endpoints' inputs and results.
        if ($this->domainMapper->hasRootModule()) {
            $helpers = [];
            foreach ($contracts as $domainContracts) {
                $helpers = [...$helpers, ...EndpointContractEmitter::helperNames($domainContracts, $this->includes)];
            }
            // Claimed already, as the helper, where the root module's own endpoints take it.
            $claimed = EndpointContractEmitter::helperNames($contracts[''] ?? [], $this->includes);
            foreach (array_diff(array_unique($helpers), $claimed) as $name) {
                $this->registerSymbol(domain: '', logicalName: null, emittedName: $name, descriptor: "the root module's endpoint helper {$name}", maps: $maps, registrations: $registrations);
            }
        }
        $this->claimedNames = $registrations;
        $this->endpointConsts = array_map(static fn (array $domainContracts): array => array_map(static fn (CollectedEndpointContract $contract): string => $contract->name, $domainContracts), $contracts);

        return $maps;
    }

    /**
     * The names the include and expand helpers take in a module, each with what it is: every one
     * they declare, where they are declared — in the module whose types use them, or in the root
     * module, where one is set, for every module's — and `Ref`, which a module that uses it imports
     * from the root module, unless it declares the expanding helpers itself beside an endpoint of
     * that name ({@see self::emitDomain()}). A module whose types use neither may name its own types
     * as it likes.
     *
     * @param list<string> $endpoints the names of the module's endpoints, each a `const` of it
     *
     * @return array<string, string>
     */
    private function includeHelperNames(string $domain, array $endpoints = []): array
    {
        $helpers = $this->domainMapper->hasRootModule() && '' === $domain
            ? array_intersect_key(self::INCLUDE_HELPERS, array_merge(...array_values($this->includeHelpers)))
            : $this->includeHelpers[$domain] ?? [];

        $names = [];
        foreach ($helpers as $kind => $code) {
            preg_match_all('/^(?:export )?(?:type|interface) (\w+)/m', $code, $declared);
            foreach ($declared[1] as $name) {
                $names[$name] = match (true) {
                    !$this->domainMapper->hasRootModule() => "the {$kind} helper {$name}",
                    '' === $domain => "the root module's {$kind} helper {$name}",
                    'expanding' === $kind && \in_array(self::REF_TYPE, $endpoints, true) => "the {$kind} helper {$name} the module declares beside its endpoint " . self::REF_TYPE,
                    self::REF_TYPE === $name => "the {$kind} helper {$name} the module imports from the root module",
                    default => null,
                };
            }
        }

        return array_filter($names, static fn (?string $what): bool => null !== $what);
    }

    /**
     * Claims an emitted name in the domain, failing on one already claimed; with a logical name, it is
     * what that name resolves to.
     *
     * @param array<string, array<string, string>> $maps
     * @param array<string, array<string, string>> $registrations
     */
    private function registerSymbol(
        string $domain,
        ?string $logicalName,
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

        if (null !== $logicalName) {
            $maps[$domain][$logicalName] = $emittedName;
        }
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
        if (!PhpFileClassLocator::loads($className)) {
            return null;
        }

        return new ReflectionClass($className);
    }

    /**
     * The names the module imports others' types under: their own, but where one is a name it takes
     * itself — every one it claims ({@see self::buildSymbolMaps()}), its endpoints' consts, and the
     * global types of TypeScript's its code may write — the module's prefixed.
     *
     * @param array<string, list<string>> $imports
     * @return array<string, array<string, string>>
     */
    private function computeForeignAliases(string $domain, array $imports): array
    {
        $usedNames = array_map(static fn (): bool => true, $this->claimedNames[$domain] ?? []) + array_fill_keys($this->endpointConsts[$domain] ?? [], true) + array_fill_keys(self::TYPESCRIPT_GLOBALS, true);
        $aliases = [];

        $importDomains = array_keys($imports);
        sort($importDomains);

        foreach ($importDomains as $importDomain) {
            $names = array_values(array_unique($imports[$importDomain]));
            sort($names);
            // The module's name as TypeScript reads one: `Admin/Checks` is `AdminChecks`.
            $prefix = preg_replace('/[^A-Za-z0-9_$]/', '', $importDomain) ?? $importDomain;
            foreach ($names as $name) {
                $alias = $name;
                if (isset($usedNames[$alias])) {
                    $alias = $prefix . $name;
                    $suffix = 0;
                    while (isset($usedNames[$alias])) {
                        $alias = $prefix . $name . (++$suffix);
                    }
                }
                $aliases[$importDomain][$name] = $alias;
                $usedNames[$alias] = true;
            }
        }

        return $aliases;
    }
}
