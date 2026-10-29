<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Collector;

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

    private readonly DomainGuesser $domainGuesser;

    private readonly PhpDocTypeHelper $docHelper;

    public function __construct(
        private readonly PhpDocShapeParser $parser = new PhpDocShapeParser(),
        ?PhpFileClassLocator $classLocator = null,
        ?DomainGuesser $domainGuesser = null,
        ?PhpDocTypeHelper $docHelper = null,
        ?ClassFileTypes $files = null,
    ) {
        $this->files = ClassFileTypes::sharedBy($files, $docHelper, $domainGuesser, $classLocator);
        $this->domainGuesser = $this->files->domainGuesser;
        $this->docHelper = $this->files->docHelper;
    }

    /**
     * @return array<string, CollectedDomain>
     */
    public function collect(string $srcDir): array
    {
        $classFiles = $this->files->classesIn($srcDir);
        $domains = [];

        foreach ($classFiles as $className => $file) {
            // A class this install can't load is no part of the application: it emits nothing, and
            // a type naming one of its aliases is told why.
            if (!$this->files->readable($file) || !$this->files->loadable($className)) {
                continue;
            }

            // A class may declare what it serialises to by importing a type as its `_self`
            // rather than writing the shape itself; it then has nothing of its own to emit.
            $importsSelf = !isset($this->files->definitions($file)['_self']) && $this->files->importsSelf($file);
            if ([] === $this->files->definitions($file) && !$importsSelf) {
                continue;
            }

            // An alias for PHPStan alone is not emitted, and an emitted one typed with it fails. An
            // import that can't be emitted fails only an alias that uses it: a file may import
            // anything for its own code. One of an alias kept for PHPStan is no symbol of any
            // module, so it is none of what the file emits; a `_self` imported from one is still
            // recorded as the class's, and fails where something names the class.
            $scope = $this->files->scope($className, $file, $srcDir, $classFiles);
            $definitions = $scope['definitions'];
            if ([] === $definitions && !$importsSelf) {
                continue;
            }
            $read = $this->files->imports($className, $file, $srcDir, $classFiles);
            $imports = $scope['imports'];
            $unresolved = $scope['unresolved'];

            $classImports = $this->files->classImports($file);
            $localAliases = array_keys($definitions);

            $domain = $this->domainGuesser->guess($srcDir, $file);
            $domains[$domain] ??= new CollectedDomain($domain);

            if ($importsSelf && isset($read['unresolved']['_self'])) {
                throw new RuntimeException(\sprintf('%s It is what %s serialises to.', $read['unresolved']['_self'], $className));
            }
            if ($importsSelf && isset($read['imports']['_self'])) {
                $domains[$domain]->selfImports[$className] = $read['imports']['_self'];
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
