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
        $shortNameMap = $this->classLocator->shortNameMap(array_keys($classFiles));
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
            // A class may declare what it serialises to by importing a type as its `_self`
            // rather than writing the shape itself; it then has nothing of its own to emit.
            $importsSelf = !isset($definitions['_self']) && 1 === preg_match('/@(?:phpstan|psalm)-import-type\s+\S+\s+from\s+\S+\s+as\s+_self\b/', $content);
            if ([] === $definitions && !$importsSelf) {
                continue;
            }

            // An import whose target is outside the source tree fails only an alias that uses it: a
            // file may import anything for its own code.
            $read = $this->docHelper->extractImportedTypesLeniently(
                content: $content,
                ownerClass: $className,
                srcDir: $srcDir,
                classFiles: $classFiles,
                shortNameMap: $shortNameMap,
                domainGuesser: $this->domainGuesser,
            );
            $imports = $read['imports'];

            $classImports = $this->docHelper->classImports($content);
            $localAliases = array_keys($definitions);

            $domain = $this->domainGuesser->guess($srcDir, $file);
            $domains[$domain] ??= new CollectedDomain($domain);

            if ($importsSelf && isset($read['unresolved']['_self'])) {
                throw new RuntimeException(\sprintf('%s It is what %s serialises to.', $read['unresolved']['_self'], $className));
            }
            if ($importsSelf && isset($imports['_self'])) {
                $domains[$domain]->selfImports[$className] = $imports['_self'];
            }

            foreach ($definitions as $alias => $definition) {
                $unresolved = $this->docHelper->unresolvedImportUsedIn($definition, $read['unresolved'], \sprintf('The @phpstan-type %s on %s', $alias, $className));
                if (null !== $unresolved) {
                    throw $unresolved;
                }

                $emittedName = $this->docHelper->emittedTypeName($alias, $className);

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
}
