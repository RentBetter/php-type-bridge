<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Collector;

use PTGS\TypeBridge\Attribute\PhpStanOnly;
use PTGS\TypeBridge\Model\CollectedDomain;
use PTGS\TypeBridge\Model\CollectedType;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Support\ClassFileTypes;
use PTGS\TypeBridge\Support\DomainGuesser;
use PTGS\TypeBridge\Support\PhpDocTypeHelper;
use PTGS\TypeBridge\Support\PhpFileClassLocator;
use RuntimeException;

final class PhpDocTypeCollector
{
    private readonly ClassFileTypes $files;

    public function __construct(
        private readonly PhpDocShapeParser $parser = new PhpDocShapeParser(),
        private readonly PhpFileClassLocator $classLocator = new PhpFileClassLocator(),
        private readonly DomainGuesser $domainGuesser = new DomainGuesser(),
        private readonly PhpDocTypeHelper $docHelper = new PhpDocTypeHelper(),
        ?ClassFileTypes $files = null,
    ) {
        $this->files = $files ?? new ClassFileTypes($docHelper, $domainGuesser, $classLocator);
    }

    /**
     * @return array<string, CollectedDomain>
     */
    public function collect(string $srcDir): array
    {
        $classFiles = $this->classLocator->classesIn($srcDir);
        $domains = [];

        foreach ($classFiles as $className => $file) {
            $content = $this->files->content($file);
            if (null === $content) {
                continue;
            }

            $definitions = $this->files->definitions($file);
            // An alias for PHPStan alone is not emitted, and an emitted one typed with it fails.
            $phpStanOnly = [];
            $only = PhpStanOnly::of($className);
            if (null !== $only) {
                foreach (array_keys($definitions) as $alias) {
                    if ($only->covers($alias)) {
                        $phpStanOnly[$alias] = ClassFileTypes::phpStanOnlyReason($alias, $className);
                    }
                }
                $definitions = array_diff_key($definitions, $phpStanOnly);
            }
            // A class may declare what it serialises to by importing a type as its `_self`
            // rather than writing the shape itself; it then has nothing of its own to emit.
            $importsSelf = !isset($definitions['_self']) && 1 === preg_match('/@(?:phpstan|psalm)-import-type\s+\S+\s+from\s+\S+\s+as\s+_self\b/', $content);
            if ([] === $definitions && !$importsSelf) {
                continue;
            }

            // An import that can't be emitted fails only an alias that uses it: a file may import
            // anything for its own code. A `_self` imported from an alias kept for PHPStan is still
            // recorded as the class's, and fails where something names the class.
            $read = $this->files->imports($className, $file, $srcDir, $classFiles);
            $imports = $read['imports'];
            $unresolved = [...$read['unresolved'], ...$read['phpStanOnly'], ...$phpStanOnly];

            $classImports = $this->files->classImports($file);
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
                $parsed = $this->parser->parse($definition);
                $failure = $this->docHelper->unresolvedImportUsedIn($parsed, $unresolved, \sprintf('The @phpstan-type %s on %s', $alias, $className));
                if (null !== $failure) {
                    throw $failure;
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
                    parsed: $this->docHelper->resolveImportedNames($parsed, $imports, $className, $classImports, $localAliases),
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
}
