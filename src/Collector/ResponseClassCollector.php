<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Collector;

use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Model\CollectedApiResponseClass;
use PTGS\TypeBridge\Model\CollectedResponseProperty;
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
     * @return array<class-string, CollectedApiResponseClass>
     */
    public function collectIndex(string $srcDir): array
    {
        $classFiles = $this->classLocator->classesIn($srcDir);
        $shortNameMap = $this->buildShortNameMap(array_keys($classFiles));
        $responses = [];

        foreach ($classFiles as $className => $file) {
            if (!class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            if ($reflection->isAbstract() || $reflection->isInterface() || !$reflection->implementsInterface(ApiResponse::class)) {
                continue;
            }

            $this->statusCodeResolver->assertResponseClass($reflection);
            $content = file_get_contents($file);
            if (false === $content) {
                continue;
            }

            $imports = $this->docHelper->extractImportedTypes(
                content: $content,
                ownerClass: $className,
                srcDir: $srcDir,
                classFiles: $classFiles,
                shortNameMap: $shortNameMap,
                domainGuesser: $this->domainGuesser,
            );

            $properties = [];
            foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->getDeclaringClass()->getName() !== $className || $property->isStatic()) {
                    continue;
                }

                $properties[] = new CollectedResponseProperty(
                    name: $property->getName(),
                    rawType: $rawType = $this->resolvePropertyType($property),
                    parsed: $this->docHelper->resolveImportedNames($this->parser->parse($rawType), $imports),
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
                imports: array_values($imports),
            );
        }

        ksort($responses);

        return $responses;
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
        $docComment = $property->getDocComment();
        if (false !== $docComment) {
            $docType = $this->docHelper->extractVarType($docComment);
            if (null !== $docType && '' !== $docType) {
                return $docType;
            }
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
