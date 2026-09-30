<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Collector;

use PTGS\TypeBridge\Attribute\PhpStanOnly;
use PTGS\TypeBridge\Model\CollectedDomain;
use PTGS\TypeBridge\Model\CollectedType;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Support\DomainGuesser;
use PTGS\TypeBridge\Support\PhpDocTypeHelper;
use PTGS\TypeBridge\Support\PhpFileClassLocator;
use ReflectionClass;
use RuntimeException;

final class PhpDocTypeCollector
{
    public function __construct(
        private readonly PhpDocShapeParser $parser = new PhpDocShapeParser(),
        private readonly PhpFileClassLocator $classLocator = new PhpFileClassLocator(),
        private readonly DomainGuesser $domainGuesser = new DomainGuesser(),
        private readonly PhpDocTypeHelper $docHelper = new PhpDocTypeHelper(),
    ) {}

    /**
     * @return array<string, CollectedDomain>
     */
    public function collect(string $srcDir): array
    {
        $classFiles = $this->classLocator->classesIn($srcDir);
        $shortNameMap = $this->buildShortNameMap(array_keys($classFiles));
        $domains = [];

        foreach ($classFiles as $className => $file) {
            $content = file_get_contents($file);
            if (false === $content) {
                continue;
            }

            $definitions = $this->docHelper->extractPhpStanTypes($content);
            $phpStanOnly = $this->phpStanOnly($className);
            if (null !== $phpStanOnly) {
                $definitions = array_filter($definitions, static fn(string $alias): bool => !$phpStanOnly->covers($alias), \ARRAY_FILTER_USE_KEY);
            }
            if ([] === $definitions) {
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

            $classImports = $this->docHelper->classImports($content);
            $localAliases = array_keys($definitions);

            $domain = $this->domainGuesser->guess($srcDir, $file);
            $domains[$domain] ??= new CollectedDomain($domain);

            foreach ($definitions as $alias => $definition) {
                $emittedName = $this->emittedTypeName($alias, $className);

                $existing = $domains[$domain]->types[$emittedName] ?? null;
                if (null !== $existing && $existing->ownerClass !== $className) {
                    throw new RuntimeException(\sprintf(
                        'Duplicate @phpstan-type "%s" in domain "%s": declared by both %s and %s. Rename one alias.',
                        $emittedName,
                        $domain,
                        $existing->ownerClass,
                        $className,
                    ));
                }

                $domains[$domain]->types[$emittedName] = new CollectedType(
                    name: $emittedName,
                    definition: $definition,
                    parsed: $this->docHelper->resolveImportedNames($this->parser->parse($definition), $imports, $className, $classImports, $localAliases),
                    sourceFile: $file,
                    domain: $domain,
                    ownerClass: $className,
                    imports: array_values($imports),
                    isSelf: '_self' === $alias,
                );
            }
        }

        ksort($domains);

        return $domains;
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

    private function shortName(string $className): string
    {
        $position = strrpos($className, '\\');
        if (false === $position) {
            return $className;
        }

        return substr($className, $position + 1);
    }

    /**
     * The class's say on which of its aliases are for PHPStan alone — typing arrays that are
     * never serialised — so they are not emitted.
     */
    private function phpStanOnly(string $className): ?PhpStanOnly
    {
        if (!class_exists($className) && !interface_exists($className) && !trait_exists($className) && !enum_exists($className)) {
            return null;
        }

        $attributes = (new ReflectionClass($className))->getAttributes(PhpStanOnly::class);

        return [] === $attributes ? null : $attributes[0]->newInstance();
    }

    private function emittedTypeName(string $alias, string $ownerClass): string
    {
        if ('_self' !== $alias) {
            return $alias;
        }

        $shortName = $this->shortName($ownerClass);
        if (enum_exists($ownerClass)) {
            return $shortName . 'Data';
        }

        return $shortName;
    }
}
