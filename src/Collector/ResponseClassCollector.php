<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Collector;

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
use RuntimeException;

final class ResponseClassCollector
{
    /** @var array<string, array<class-string, CollectedApiResponseClass>> by source directory */
    private array $indexes = [];

    /** @var array<string, array{imports: array<string, ImportedType>, unresolved: array<string, string>, classImports: array<string, string>, localAliases: list<string>}> by source directory and class */
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
        $shortNameMap = $this->buildShortNameMap(array_keys($classFiles));
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
            $context = $this->typeContext($className, $file, $srcDir, $classFiles, $shortNameMap, inherited: false);
            if (null === $context) {
                continue;
            }

            $properties = [];
            foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->isStatic()) {
                    continue;
                }

                // An inherited property is on the wire as much as the class's own — the response
                // subscriber writes every public property — so it is published too, read in the file
                // that declares it and converted in that file's scope, whatever the response's own
                // file imports under the same names. Only a class in the source tree counts: a
                // library's base class is not part of the contract.
                $declaringClass = $property->getDeclaringClass()->getName();
                $inherited = $declaringClass !== $className;
                $declared = $context;
                if ($inherited) {
                    $declaringFile = $classFiles[$declaringClass] ?? null;
                    $declared = null === $declaringFile ? null : $this->typeContext($declaringClass, $declaringFile, $srcDir, $classFiles, $shortNameMap, inherited: true);
                    if (null === $declared) {
                        continue;
                    }
                }

                $rawType = $this->resolvePropertyType($property);
                foreach ($declared['unresolved'] as $alias => $why) {
                    if (1 === preg_match('/(?<![\\w\\\\])' . preg_quote($alias, '/') . '(?![\\w\\\\])/', $rawType)) {
                        throw new RuntimeException(\sprintf('%s The %s property %s::$%s is typed with it.', $why, $className, $declaringClass, $property->getName()));
                    }
                }

                $parsed = $this->docHelper->resolveImportedNames(
                    $this->parser->parse($rawType),
                    $declared['imports'],
                    $declaringClass,
                    $declared['classImports'],
                    $declared['localAliases'],
                );
                $properties[] = new CollectedResponseProperty(
                    name: $property->getName(),
                    rawType: $rawType,
                    parsed: $parsed,
                    // `?T` is the TS-optional form, as it is in a `_self` shape: the key is left off
                    // the wire when null (a side-load not asked for). `T|null` parses as a required
                    // nullable and stays a key that is always present.
                    optional: $parsed instanceof NullableType && $parsed->optional,
                    imports: $inherited ? array_values($declared['imports']) : null,
                );
            }

            $responses[$className] = new CollectedApiResponseClass(
                className: $className,
                name: $reflection->getShortName(),
                domain: $this->domainGuesser->guess($srcDir, $file),
                sourceFile: $file,
                status: $this->statusCodeResolver->resolve($reflection),
                error: $this->statusCodeResolver->isError($reflection),
                properties: $properties,
                imports: array_values($context['imports']),
            );
        }

        ksort($responses);

        return $responses;
    }

    /**
     * What a class's file says its types mean: the types it imports, the classes it `use`s, and
     * the aliases it declares — what a property's annotation in that file is read against.
     *
     * A base class is read for its inherited properties only, from wherever its child is emitted:
     * the aliases it declares itself become imports from its own module, and an import whose
     * target is not in the source tree is set aside rather than thrown — its protected properties
     * may name anything — and fails only a published property that is typed with it.
     *
     * @param array<string, string> $classFiles
     * @param array<string, list<string>> $shortNameMap
     * @return array{imports: array<string, ImportedType>, unresolved: array<string, string>, classImports: array<string, string>, localAliases: list<string>}|null
     */
    private function typeContext(string $className, string $file, string $srcDir, array $classFiles, array $shortNameMap, bool $inherited): ?array
    {
        $key = $srcDir . "\0" . $className;
        if (isset($this->typeContexts[$key])) {
            return $this->typeContexts[$key];
        }

        $content = file_get_contents($file);
        if (false === $content) {
            return null;
        }

        $localAliases = array_keys($this->docHelper->extractPhpStanTypes($content));
        $classImports = $this->docHelper->classImports($content);

        if (!$inherited) {
            return $this->typeContexts[$key] = [
                'imports' => $this->docHelper->extractImportedTypes($content, $className, $srcDir, $classFiles, $shortNameMap, $this->domainGuesser),
                'unresolved' => [],
                'classImports' => $classImports,
                'localAliases' => $localAliases,
            ];
        }

        $read = $this->docHelper->extractImportedTypesLeniently($content, $className, $srcDir, $classFiles, $shortNameMap, $this->domainGuesser);
        $imports = $read['imports'];
        $domain = $this->domainGuesser->guess($srcDir, $file);
        foreach ($localAliases as $alias) {
            $imports[$alias] = new ImportedType(
                localAlias: $alias,
                targetClass: $className,
                targetTypeName: $this->docHelper->emittedAliasName($alias, $className),
                targetDomain: $domain,
            );
        }

        return $this->typeContexts[$key] = [
            'imports' => $imports,
            'unresolved' => $read['unresolved'],
            'classImports' => $classImports,
            'localAliases' => [],
        ];
    }

    /**
     * @param list<string> $classNames
     * @return array<string, list<string>>
     */
    private function buildShortNameMap(array $classNames): array
    {
        $shortNameMap = [];

        foreach ($classNames as $className) {
            $shortNameMap[$this->shortName($className)][] = $className;
        }

        return $shortNameMap;
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

    private function shortName(string $className): string
    {
        $position = strrpos($className, '\\');
        if (false === $position) {
            return $className;
        }

        return substr($className, $position + 1);
    }
}
