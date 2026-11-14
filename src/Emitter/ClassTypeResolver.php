<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

use BackedEnum;
use JsonSerializable;
use PTGS\TypeBridge\Model\CollectedApiResponseClass;
use PTGS\TypeBridge\Model\CollectedDomain;
use PTGS\TypeBridge\Model\CollectedType;
use PTGS\TypeBridge\Model\ImportedType;
use PTGS\TypeBridge\Support\PhpFileClassLocator;
use PTGS\TypeBridge\Support\RootSources;
use ReflectionClass;
use RuntimeException;


/**
 * The TypeScript type for a class named in a shape: the JSON the class serialises to.
 *
 * `total: MoneyInterface` in a shape means the object PHPStan sees there, and json_encode writes
 * what that object serialises itself to. That is declared by the class's `_self` — written out,
 * or imported from where the shape is declared (`@phpstan-import-type MoneyData from
 * AbstractV2Normalizer as _self`), when the class then emits nothing under its own name — or, when
 * it has neither, by the nearest parent or interface that does. Failing that, the emitter that claims the class may publish the
 * type it writes for it: an enum's, typically.
 */
final class ClassTypeResolver
{
    /** @var array<string, CollectedType> keyed by the class that declares the `_self` */
    private array $selfShapes = [];

    /** @var array<string, ImportedType> keyed by the class that imports its `_self` */
    private array $selfImports = [];

    /** @var array<string, CollectedDomain> */
    private array $domains;

    /** @var array<string, CollectedApiResponseClass> keyed by class */
    private array $responses = [];

    /** @var array<string, EmitImport> */
    private array $resolved = [];

    /**
     * @param array<string, CollectedDomain>                 $domains
     * @param array<string, list<CollectedApiResponseClass>> $responses
     */
    public function __construct(
        array $domains,
        private readonly EmittedNames $names,
        private readonly EmitterRegistry $registry,
        private readonly RootSources $rootSources = new RootSources(),
        array $responses = [],
    ) {
        $this->domains = $domains;
        foreach ($domains as $domain) {
            foreach ($domain->types as $type) {
                if ($type->isSelf) {
                    $this->selfShapes[$type->ownerClass] = $type;
                }
            }
            $this->selfImports += $domain->selfImports;
        }
        foreach ($responses as $domainResponses) {
            foreach ($domainResponses as $response) {
                $this->responses[$response->className] = $response;
            }
        }
    }

    public function resolve(string $class): EmitImport
    {
        return $this->resolved[$class] ??= $this->lookUp($class);
    }

    private function lookUp(string $class): EmitImport
    {
        if (!PhpFileClassLocator::loads($class)) {
            throw new RuntimeException(\sprintf('A shape names "%s" as a type, and there is no such class.', $class));
        }

        // A response is sent as the interface of its public properties the module declares for it.
        if (isset($this->responses[$class])) {
            $response = $this->responses[$class];

            return new EmitImport($response->domain, $this->names->responseDeclarationName($response));
        }

        $reflection = new ReflectionClass($class);
        foreach ($this->lineage($reflection) as $candidate) {
            if (isset($this->selfShapes[$candidate])) {
                $shape = $this->selfShapes[$candidate];

                return new EmitImport($shape->domain, $this->names->typeDeclarationName($shape));
            }

            if (isset($this->selfImports[$candidate])) {
                return $this->importedSelf($candidate, $this->selfImports[$candidate]);
            }
        }

        $emitter = $this->registry->typeSymbolEmitterFor($reflection);
        if (null !== $emitter) {
            return $this->rootSources->place($emitter->typeSymbol($reflection), $reflection);
        }

        // A backed enum that doesn't serialise itself is written as its backing value: its values, as
        // `value-of<…>` names them, declared in its module ({@see TypeScriptEmitter::emit()}).
        if (self::writtenAsItsValue($class)) {
            return new EmitImport($this->names->enumDomain($class), $this->names->enumName($class));
        }

        throw new RuntimeException(\sprintf(
            'A shape names %s as a type, meaning the JSON it serialises to, but neither it nor a parent or '
            . 'interface declares a `_self` shape in the source tree, and no emitter publishes a type for it. Give it a '
            . '`@phpstan-type _self` describing what jsonSerialize() returns — or, if the shape naming it is '
            . 'never serialised, mark that shape\'s class #[PhpStanOnly].',
            $class,
        ));
    }

    /**
     * Whether the class is a backed enum json_encode writes as its backing value: one that doesn't
     * serialise itself (JsonSerializable).
     */
    public static function writtenAsItsValue(string $class): bool
    {
        return PhpFileClassLocator::loadsEnum($class) && is_subclass_of($class, BackedEnum::class) && !is_subclass_of($class, JsonSerializable::class);
    }

    /**
     * The type a class imports as its `_self`, under the name its declaring module emits it as.
     */
    private function importedSelf(string $class, ImportedType $import): EmitImport
    {
        $target = $this->domains[$import->targetDomain]->types[$import->targetTypeName] ?? null;
        if (null === $target) {
            throw new RuntimeException(\sprintf(
                '%s imports %s as its `_self`, but %s declares no such type that is emitted.',
                $class,
                $import->targetTypeName,
                $import->targetClass,
            ));
        }

        return new EmitImport($target->domain, $this->names->typeDeclarationName($target));
    }

    /**
     * The class, then its parents, then its interfaces: nearest first.
     *
     * @param ReflectionClass<object> $class
     * @return list<string>
     */
    private function lineage(ReflectionClass $class): array
    {
        $lineage = [];
        for ($current = $class; false !== $current; $current = $current->getParentClass()) {
            $lineage[] = $current->getName();
        }

        return [...$lineage, ...$class->getInterfaceNames()];
    }
}
