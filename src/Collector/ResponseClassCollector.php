<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Collector;

use PTGS\TypeBridge\Attribute\PhpStanOnly;
use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Model\CollectedApiResponseClass;
use PTGS\TypeBridge\Model\CollectedResponseProperty;
use PTGS\TypeBridge\Model\ImportedType;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Resolver\StatusCodeResolver;
use PTGS\TypeBridge\Support\ClassFileTypes;
use PTGS\TypeBridge\Support\DomainGuesser;
use PTGS\TypeBridge\Support\PhpDocTypeHelper;
use PTGS\TypeBridge\Support\PhpFileClassLocator;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionUnionType;

final class ResponseClassCollector
{
    /** @var array<string, array<class-string, CollectedApiResponseClass>> by source directory */
    private array $indexes = [];

    private readonly ClassFileTypes $files;

    public function __construct(
        private readonly StatusCodeResolver $statusCodeResolver = new StatusCodeResolver(),
        private readonly PhpDocShapeParser $parser = new PhpDocShapeParser(),
        private readonly PhpFileClassLocator $classLocator = new PhpFileClassLocator(),
        private readonly DomainGuesser $domainGuesser = new DomainGuesser(),
        private readonly PhpDocTypeHelper $docHelper = new PhpDocTypeHelper(),
        ?ClassFileTypes $files = null,
    ) {
        $this->files = $files ?? new ClassFileTypes($docHelper, $domainGuesser, $classLocator);
    }

    /**
     * @return array<string, list<CollectedApiResponseClass>>
     */
    public function collect(string $srcDir): array
    {
        $domains = [];
        foreach ($this->collectIndex($srcDir) as $response) {
            $domains[$response->domain] ??= [];
            $domains[$response->domain][] = $response;
        }

        ksort($domains);

        return $domains;
    }

    /**
     * Read once per source directory, however often it is asked for: a generation run asks for the
     * grouped and the indexed form both.
     *
     * @return array<class-string, CollectedApiResponseClass>
     */
    public function collectIndex(string $srcDir): array
    {
        return $this->indexes[$srcDir] ??= $this->readIndex($srcDir);
    }

    /**
     * @return array<class-string, CollectedApiResponseClass>
     */
    private function readIndex(string $srcDir): array
    {
        $classFiles = $this->classLocator->classesIn($srcDir);
        $responses = [];

        foreach ($classFiles as $className => $file) {
            if (!$this->classLocator->isLoadable($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            if ($reflection->isAbstract() || $reflection->isInterface() || !$reflection->implementsInterface(ApiResponse::class)) {
                continue;
            }

            $this->statusCodeResolver->assertResponseClass($reflection);
            $context = $this->typeContext($className, $file, $srcDir, $classFiles);
            if (null === $context) {
                continue;
            }

            $status = $this->statusCodeResolver->resolve($reflection);
            $properties = [];
            // A 204 is sent with no body at all: nothing it declares or inherits is published.
            foreach (204 === $status ? [] : $reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->isStatic()) {
                    continue;
                }

                // An inherited property is on the wire as much as the class's own — the response
                // subscriber writes every public property — so it is published too. Every property
                // is read in the file it is written in — the class's, a base class's, a trait's — and
                // converted in that file's scope, whatever another file imports under the same
                // names, and whichever module the response is in.
                $declaringClass = self::writtenIn($property, $reflection, $classFiles);
                $declared = $this->typeContext($declaringClass, $classFiles[$declaringClass], $srcDir, $classFiles);
                if (null === $declared) {
                    continue;
                }

                $rawType = $this->resolvePropertyType($property);
                $unresolved = $this->docHelper->unresolvedImportUsedIn($rawType, $declared['unresolved'], \sprintf('The %s property %s::$%s', $className, $declaringClass, $property->getName()));
                if (null !== $unresolved) {
                    throw $unresolved;
                }

                // Names are the file's; `self::` is the class, which for a trait is the one using it.
                $parsed = $this->docHelper->resolveImportedNames($this->parser->parse($rawType), $declared['imports'], $declaringClass, $declared['classImports'], selfClass: $property->getDeclaringClass()->getName());
                $properties[] = new CollectedResponseProperty(
                    name: $property->getName(),
                    rawType: $rawType,
                    parsed: $parsed,
                    // `?T` is the TS-optional form, as it is in a `_self` shape: the key is left off
                    // the wire when null (a side-load not asked for). `T|null` parses as a required
                    // nullable and stays a key that is always present.
                    optional: $parsed instanceof NullableType && $parsed->optional,
                    imports: array_values($declared['imports']),
                    writtenIn: $declaringClass === $className ? null : $declaringClass,
                    writtenInDomain: $declaringClass === $className ? null : $declared['domain'],
                );
            }

            $responses[$className] = new CollectedApiResponseClass(
                className: $className,
                name: $reflection->getShortName(),
                domain: $this->domainGuesser->guess($srcDir, $file),
                sourceFile: $file,
                status: $status,
                error: $this->statusCodeResolver->isError($reflection),
                properties: $properties,
                imports: array_values($context['imports']),
            );
        }

        ksort($responses);

        return $responses;
    }

    /**
     * The scope a property written in a class's file is read in: the types the file imports and the
     * aliases it declares itself — both as imports, the latter from its own module, so a property
     * converted in this scope finds them wherever it is emitted — the classes it `use`s, and its
     * module. An import that can't be emitted, and an alias the class keeps for PHPStan, are set
     * aside: they fail only a published property typed with them.
     *
     * @param array<string, string> $classFiles
     *
     * @return array{imports: array<string, ImportedType>, unresolved: array<string, string>, classImports: array<string, string>, domain: string}|null
     */
    private function typeContext(string $className, string $file, string $srcDir, array $classFiles): ?array
    {
        if (null === $this->files->content($file)) {
            return null;
        }

        $read = $this->files->imports($className, $file, $srcDir, $classFiles);
        $imports = array_diff_key($read['imports'], $read['phpStanOnly']);
        $unresolved = [...$read['unresolved'], ...$read['phpStanOnly']];
        $domain = $this->domainGuesser->guess($srcDir, $file);
        $phpStanOnly = PhpStanOnly::of($className);
        foreach (array_keys($this->files->definitions($file)) as $alias) {
            if (null !== $phpStanOnly && $phpStanOnly->covers($alias)) {
                $unresolved[$alias] = ClassFileTypes::phpStanOnlyReason($alias, $className);

                continue;
            }
            $imports[$alias] = new ImportedType(
                localAlias: $alias,
                targetClass: $className,
                targetTypeName: $this->docHelper->emittedTypeName($alias, $className),
                targetDomain: $domain,
                sourceAlias: $alias,
            );
        }

        return [
            'imports' => $imports,
            'unresolved' => $unresolved,
            'classImports' => $this->files->classImports($file),
            'domain' => $domain,
        ];
    }

    /**
     * The class or trait whose file a property is written in. Reflection names the class a trait's
     * property is used in; the trait that declares it — the innermost, its doc comment the
     * property's — is where its annotation was written and its names are imported.
     *
     * A file outside the source tree — a library's trait or base class — is not read here, so its
     * property is read in the nearest file of the tree that has it: the class using the trait, or
     * the class of the tree nearest the library's base. The subscriber sends it all the same.
     *
     * @param ReflectionClass<object> $response
     * @param array<string, string>   $classFiles
     */
    private static function writtenIn(ReflectionProperty $property, ReflectionClass $response, array $classFiles): string
    {
        $class = $property->getDeclaringClass();
        $traits = $class->getTraits();
        while ([] !== $traits) {
            $trait = array_shift($traits);
            if (isset($classFiles[$trait->getName()]) && $trait->hasProperty($property->getName()) && $trait->getProperty($property->getName())->getDocComment() === $property->getDocComment()) {
                $class = $trait;
                $traits = $trait->getTraits();
            }
        }
        if (isset($classFiles[$class->getName()])) {
            return $class->getName();
        }

        $nearest = $response->getName();
        for ($current = $response; false !== $current && $current->getName() !== $class->getName(); $current = $current->getParentClass()) {
            if (isset($classFiles[$current->getName()])) {
                $nearest = $current->getName();
            }
        }

        return $nearest;
    }

    private function resolvePropertyType(ReflectionProperty $property): string
    {
        $docType = $this->docHelper->extractPropertyType($property);
        if (null !== $docType) {
            return $docType;
        }

        $type = $property->getType();
        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }

        if ($type instanceof ReflectionUnionType) {
            $names = [];
            foreach ($type->getTypes() as $namedType) {
                if (!$namedType instanceof ReflectionNamedType) {
                    continue;
                }

                $names[] = $namedType->getName();
            }

            return implode('|', $names);
        }

        return 'mixed';
    }
}
