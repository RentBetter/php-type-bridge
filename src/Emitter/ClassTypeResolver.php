<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter;

use PTGS\TypeBridge\Model\CollectedDomain;
use PTGS\TypeBridge\Model\CollectedType;
use ReflectionClass;
use RuntimeException;

/**
 * The TypeScript type for a class named in a shape: the JSON the class serialises to.
 *
 * `total: MoneyInterface` in a shape means the object PHPStan sees there, and json_encode writes
 * what that object serialises itself to. That is declared by the class's `_self` — or, when it
 * has none of its own, by the nearest parent or interface that does, as `MoneyModelV2` gets its
 * shape from `MoneyInterface`. Failing that, the emitter that claims the class may publish the
 * type it writes for it: an enum's, typically.
 */
final class ClassTypeResolver
{
    /** @var array<string, CollectedType> keyed by the class that declares the `_self` */
    private array $selfShapes = [];

    /** @var array<string, EmitImport> */
    private array $resolved = [];

    /**
     * @param array<string, CollectedDomain> $domains
     */
    public function __construct(
        array $domains,
        private readonly EmittedNames $names,
        private readonly EmitterRegistry $registry,
    ) {
        foreach ($domains as $domain) {
            foreach ($domain->types as $type) {
                if ($type->isSelf) {
                    $this->selfShapes[$type->ownerClass] = $type;
                }
            }
        }
    }

    public function resolve(string $class): EmitImport
    {
        return $this->resolved[$class] ??= $this->lookUp($class);
    }

    private function lookUp(string $class): EmitImport
    {
        if (!class_exists($class) && !interface_exists($class) && !enum_exists($class)) {
            throw new RuntimeException(\sprintf('A shape names "%s" as a type, and there is no such class.', $class));
        }

        $reflection = new ReflectionClass($class);
        foreach ($this->lineage($reflection) as $candidate) {
            if (isset($this->selfShapes[$candidate])) {
                $shape = $this->selfShapes[$candidate];

                return new EmitImport($shape->domain, $this->names->typeDeclarationName($shape));
            }
        }

        $emitter = $this->registry->typeSymbolEmitterFor($reflection);
        if (null !== $emitter) {
            return $emitter->typeSymbol($reflection);
        }

        throw new RuntimeException(\sprintf(
            'A shape names %s as a type, meaning the JSON it serialises to, but neither it nor a parent or '
            . 'interface declares a `_self` shape, and no emitter publishes a type for it. Give it a '
            . '`@phpstan-type _self` describing what jsonSerialize() returns — or, if the shape naming it is '
            . 'never serialised, mark that shape\'s class #[PhpStanOnly].',
            $class,
        ));
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
