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

    /** @var array<string, array{imports: array<string, ImportedType>, unresolved: array<string, string>, classImports: array<string, string>}> by source directory and class */
    private array $typeContexts = [];

    public function __construct(
        private readonly StatusCodeResolver $statusCodeResolver = new StatusCodeResolver(),
        private readonly PhpDocShapeParser $parser = new PhpDocShapeParser(),
        private readonly PhpFileClassLocator $classLocator = new PhpFileClassLocator(),
        private readonly DomainGuesser $domainGuesser = new DomainGuesser(),
        private readonly PhpDocTypeHelper $docHelper = new PhpDocTypeHelper(),
    ) {}

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
        $shortNameMap = $this->classLocator->shortNameMap(array_keys($classFiles));
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
            $context = $this->typeContext($className, $file, $srcDir, $classFiles, $shortNameMap);
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
                // names. Only a file in the source tree counts: a library's is not the contract.
                $declaringClass = self::writtenIn($property);
                $declaringFile = $classFiles[$declaringClass] ?? null;
                $declared = null === $declaringFile ? null : $this->typeContext($declaringClass, $declaringFile, $srcDir, $classFiles, $shortNameMap);
                if (null === $declared) {
                    continue;
                }

                $rawType = $this->resolvePropertyType($property);
                $unresolved = $this->docHelper->unresolvedImportUsedIn($rawType, $declared['unresolved'], \sprintf('The %s property %s::$%s', $className, $declaringClass, $property->getName()));
                if (null !== $unresolved) {
                    throw $unresolved;
                }

                $parsed = $this->docHelper->resolveImportedNames($this->parser->parse($rawType), $declared['imports'], $declaringClass, $declared['classImports']);
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
     * What a class's file says its types mean, read once per class: the types it imports and the
     * aliases it declares itself — both as imports, the latter from its own module, so a property
     * converted in this scope finds them wherever it is emitted — and the classes it `use`s. An
     * import whose target is outside the source tree is set aside rather than thrown: a file may
     * import anything for its own code, and it fails only a published property typed with it.
     *
     * @param array<string, string> $classFiles
     * @param array<string, list<string>> $shortNameMap
     * @return array{imports: array<string, ImportedType>, unresolved: array<string, string>, classImports: array<string, string>}|null
     */
    private function typeContext(string $className, string $file, string $srcDir, array $classFiles, array $shortNameMap): ?array
    {
        $key = $srcDir . "\0" . $className;
        if (isset($this->typeContexts[$key])) {
            return $this->typeContexts[$key];
        }

        $content = file_get_contents($file);
        if (false === $content) {
            return null;
        }

        $read = $this->docHelper->extractImportedTypesLeniently($content, $className, $srcDir, $classFiles, $shortNameMap, $this->domainGuesser);
        $imports = $read['imports'];
        $domain = $this->domainGuesser->guess($srcDir, $file);
        $phpStanOnly = (class_exists($className) || trait_exists($className)) ? ((new ReflectionClass($className))->getAttributes(PhpStanOnly::class)[0] ?? null)?->newInstance() : null;
        foreach (array_keys($this->docHelper->extractPhpStanTypes($content)) as $alias) {
            // An alias for PHPStan alone is never emitted, so there is nothing to import it from.
            if (null !== $phpStanOnly && $phpStanOnly->covers($alias)) {
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

        return $this->typeContexts[$key] = [
            'imports' => $imports,
            'unresolved' => $read['unresolved'],
            'classImports' => $this->docHelper->classImports($content),
        ];
    }


    /**
     * The class or trait whose file a property is written in. Reflection names the class a trait's
     * property is used in; the trait that declares it — the innermost, its doc comment the
     * property's — is where its annotation was written and its names are imported.
     */
    private static function writtenIn(ReflectionProperty $property): string
    {
        $class = $property->getDeclaringClass();
        $traits = $class->getTraits();
        while ([] !== $traits) {
            $trait = array_shift($traits);
            if ($trait->hasProperty($property->getName()) && $trait->getProperty($property->getName())->getDocComment() === $property->getDocComment()) {
                $class = $trait;
                $traits = $trait->getTraits();
            }
        }

        return $class->getName();
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
