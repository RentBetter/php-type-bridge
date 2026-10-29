<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

use PHPStan\PhpDocParser\Ast\PhpDoc\TypeAliasImportTagValueNode;
use PTGS\TypeBridge\Attribute\PhpStanOnly;
use PTGS\TypeBridge\Model\ImportedType;
use InvalidArgumentException;

/**
 * What a class's file says its types mean — the aliases it declares, the types it imports, the
 * classes it `use`s — worked out once per file however many collectors, properties and queries ask,
 * and the same way for each of them; and the classes a source tree declares, scanned once. What is
 * kept is what was read out of a file, not its source.
 *
 * A file may import anything for its own code, so an import that can't be followed is set aside
 * with why, and fails only what is typed with it ({@see PhpDocTypeHelper::unresolvedImportUsedIn()}):
 * one whose target is outside the source tree, and one of an alias its class keeps for PHPStan
 * (#[PhpStanOnly]), which is never emitted however many other classes emit a type of that name.
 */
final class ClassFileTypes
{
    /** @var array<string, array{definitions: array<string, string>, classImports: array<string, string>, importTags: list<TypeAliasImportTagValueNode>}|false> by file; false where it can't be read */
    private array $files = [];

    /** @var array<string, bool> by class */
    private array $loadable = [];

    /** @var array<string, PhpStanOnly|null> by class */
    private array $phpStanOnly = [];

    /** @var array<string, array<string, string>> by source directory: class => file */
    private array $classes = [];

    /** @var array<string, array{imports: array<string, ImportedType>, unresolved: array<string, string>, phpStanOnly: array<string, string>}> by source directory and class */
    private array $imports = [];

    /** @var array<string, array<string, list<string>>> by source directory */
    private array $shortNameMaps = [];

    /** @var array<string, true> by source directory and class: the imports being followed, in the order they began */
    private array $following = [];

    /** @var array<string, true> by source directory and class: those read against one only partly followed */
    private array $cutShort = [];

    public function __construct(
        public readonly PhpDocTypeHelper $docHelper = new PhpDocTypeHelper(),
        public readonly DomainGuesser $domainGuesser = new DomainGuesser(),
        public readonly PhpFileClassLocator $classLocator = new PhpFileClassLocator(),
    ) {}

    /**
     * The reader a collector reads with: the one it is given, or one made of the helpers it is given.
     * A helper given beside a reader must be the reader's own — a collector placing a type in one
     * module while the reader places its imports in another is a mistake made silently otherwise.
     */
    public static function sharedBy(?self $files, ?PhpDocTypeHelper $docHelper, ?DomainGuesser $domainGuesser, ?PhpFileClassLocator $classLocator): self
    {
        if (null === $files) {
            return new self($docHelper ?? new PhpDocTypeHelper(), $domainGuesser ?? new DomainGuesser(), $classLocator ?? new PhpFileClassLocator());
        }

        foreach (['docHelper' => [$docHelper, $files->docHelper], 'domainGuesser' => [$domainGuesser, $files->domainGuesser], 'classLocator' => [$classLocator, $files->classLocator]] as $name => [$given, $own]) {
            if (null !== $given && $given !== $own) {
                throw new InvalidArgumentException(\sprintf('A %s given beside a ClassFileTypes must be the one it reads with: give it to the ClassFileTypes, and the collector takes it from there.', $name));
            }
        }

        return $files;
    }

    /**
     * The classes a source tree declares, class => file, scanned once however many collectors ask.
     *
     * @return array<string, string>
     */
    public function classesIn(string $srcDir): array
    {
        return $this->classes[$srcDir] ??= $this->classLocator->classesIn($srcDir);
    }

    /**
     * Whether the file can be read.
     */
    public function readable(string $file): bool
    {
        return false !== $this->read($file);
    }

    /**
     * Whether the class, interface or trait can be loaded in this install
     * ({@see PhpFileClassLocator::isLoadable()}), asked once. One kept under src/ for tooling, whose
     * declaration needs a require-dev package, can't be without it: it is no part of the
     * application, and nothing it declares is emitted.
     *
     * @phpstan-assert-if-true class-string $class
     */
    public function loadable(string $class): bool
    {
        return $this->loadable[$class] ??= $this->classLocator->isLoadable($class);
    }

    /**
     * The class's say on which of its aliases are for PHPStan alone ({@see PhpStanOnly::of()}),
     * asked once.
     */
    public function phpStanOnly(string $class): ?PhpStanOnly
    {
        if (!\array_key_exists($class, $this->phpStanOnly)) {
            $this->phpStanOnly[$class] = $this->loadable($class) ? PhpStanOnly::of($class) : null;
        }

        return $this->phpStanOnly[$class];
    }

    /**
     * Whether the file imports a type as its class's `_self` (`… as _self`): what the class
     * serialises to, declared elsewhere.
     */
    public function importsSelf(string $file): bool
    {
        foreach ($this->read($file)['importTags'] ?? [] as $tag) {
            if ('_self' === $tag->importedAs) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every alias the file declares, #[PhpStanOnly] ones included: alias => definition.
     *
     * @return array<string, string>
     */
    public function definitions(string $file): array
    {
        return $this->read($file)['definitions'] ?? [];
    }

    /**
     * The file's `use` statements for classes, as alias => fully qualified name.
     *
     * @return array<string, string>
     */
    public function classImports(string $file): array
    {
        return $this->read($file)['classImports'] ?? [];
    }

    /**
     * What the file declares, imports and `use`s, read out of it once.
     *
     * @return array{definitions: array<string, string>, classImports: array<string, string>, importTags: list<TypeAliasImportTagValueNode>}|false
     */
    private function read(string $file): array|false
    {
        if (\array_key_exists($file, $this->files)) {
            return $this->files[$file];
        }

        $content = is_file($file) ? file_get_contents($file) : false;

        return $this->files[$file] = false === $content ? false : [
            'definitions' => $this->docHelper->extractPhpStanTypes($content),
            'classImports' => $this->docHelper->classImports($content),
            'importTags' => $this->docHelper->importTags($content),
        ];
    }

    /**
     * The types the class imports, by the name it imports each as, each as the class that declares
     * it says: one imported from a class that only imports it itself is that class's import, followed
     * to where the alias is declared, as PHPStan follows it. `unresolved` is those whose target can't
     * be found or can't be loaded in this install, and `phpStanOnly` those of an alias kept for
     * PHPStan — still among `imports`, for what only reads its keys — each with why it can't be
     * emitted.
     *
     * @param array<string, string> $classFiles
     *
     * @return array{imports: array<string, ImportedType>, unresolved: array<string, string>, phpStanOnly: array<string, string>}
     */
    public function imports(string $class, string $file, string $srcDir, array $classFiles): array
    {
        $key = $srcDir . "\0" . $class;
        if (isset($this->imports[$key])) {
            return $this->imports[$key];
        }

        $read = $this->docHelper->importedTypes(
            $this->read($file)['importTags'] ?? [],
            $class,
            $srcDir,
            $classFiles,
            $this->shortNameMaps[$srcDir] ??= $this->classLocator->shortNameMap(array_keys($classFiles)),
            $this->domainGuesser,
        );
        // Read while it is followed: an import that leads back here is left where it points. What
        // was read since it began is read against that, so none of it is kept: read again, once
        // this is done, it follows this class's imports through.
        if (isset($this->following[$key])) {
            $since = \array_slice(array_keys($this->following), array_search($key, array_keys($this->following), true) + 1);
            $this->cutShort += array_fill_keys($since, true);

            return ['imports' => $read['imports'], 'unresolved' => $read['unresolved'], 'phpStanOnly' => []];
        }
        $this->following[$key] = true;

        $imports = [];
        $unresolved = $read['unresolved'];
        $phpStanOnly = [];
        foreach ($read['imports'] as $alias => $import) {
            $followed = $this->declaredAt($import, $srcDir, $classFiles);
            if (\is_string($followed)) {
                $unresolved[$alias] = $followed;

                continue;
            }
            $import = $followed;
            if (!$this->loadable($import->targetClass)) {
                $unresolved[$alias] = self::unloadableReason($import->sourceName(), $import->targetClass);

                continue;
            }
            $imports[$alias] = $import;
            if ($this->phpStanOnly($import->targetClass)?->covers($import->sourceName()) ?? false) {
                $phpStanOnly[$alias] = self::phpStanOnlyReason($import->sourceName(), $import->targetClass);
            }
        }

        unset($this->following[$key]);
        $result = ['imports' => $imports, 'unresolved' => $unresolved, 'phpStanOnly' => $phpStanOnly];
        if (isset($this->cutShort[$key])) {
            unset($this->cutShort[$key]);

            return $result;
        }

        return $this->imports[$key] = $result;
    }

    /**
     * The names a class's types are read with, the same for every collector: what it imports that
     * is emitted, and the aliases it declares that are. An import of an alias kept for PHPStan, and
     * an alias the class keeps for PHPStan itself, are set aside with why a type using it can't be
     * emitted, beside the imports that can't be followed.
     *
     * @param array<string, string> $classFiles
     *
     * @return array{imports: array<string, ImportedType>, definitions: array<string, string>, unresolved: array<string, string>}
     */
    public function scope(string $class, string $file, string $srcDir, array $classFiles): array
    {
        $read = $this->imports($class, $file, $srcDir, $classFiles);
        $definitions = $this->emittedDefinitions($class, $file);
        $unresolved = [...$read['unresolved'], ...$read['phpStanOnly']];
        foreach (array_keys(array_diff_key($this->definitions($file), $definitions)) as $alias) {
            $unresolved[$alias] = self::phpStanOnlyReason($alias, $class);
        }

        return ['imports' => array_diff_key($read['imports'], $read['phpStanOnly']), 'definitions' => $definitions, 'unresolved' => $unresolved];
    }

    /**
     * The aliases the class declares that are emitted: all but those it keeps for PHPStan. Read off
     * the file and the class alone, with no import followed.
     *
     * @return array<string, string>
     */
    public function emittedDefinitions(string $class, string $file): array
    {
        $only = $this->phpStanOnly($class);

        return null === $only
            ? $this->definitions($file)
            : array_filter($this->definitions($file), static fn (string $alias): bool => !$only->covers($alias), \ARRAY_FILTER_USE_KEY);
    }

    /**
     * The import, followed to the class that declares the alias it names: where its target only
     * imports that alias, the target's import of it — itself followed — under the name this file
     * gives it. Why it can't be followed, where the target's import of it can't be; the import as it
     * is where the target declares the alias, or neither declares nor imports it — reading it there
     * says it is missing.
     *
     * @param array<string, string> $classFiles
     */
    private function declaredAt(ImportedType $import, string $srcDir, array $classFiles): ImportedType|string
    {
        $file = $classFiles[$import->targetClass] ?? null;
        if (null === $file || isset($this->definitions($file)[$import->sourceName()])) {
            return $import;
        }

        $further = $this->imports($import->targetClass, $file, $srcDir, $classFiles);
        $next = $further['imports'][$import->sourceName()] ?? null;
        if (null === $next) {
            return $further['unresolved'][$import->sourceName()] ?? $import;
        }

        return new ImportedType(
            localAlias: $import->localAlias,
            targetClass: $next->targetClass,
            targetTypeName: $next->targetTypeName,
            targetDomain: $next->targetDomain,
            sourceAlias: $next->sourceName(),
        );
    }

    /**
     * Why a type can't be typed with an alias a class this install can't load declares.
     */
    public static function unloadableReason(string $alias, string $class): string
    {
        return \sprintf('%s cannot be loaded in this install — its declaration needs a class that is not installed, a require-dev one, say — so its "%s" is never emitted.', $class, $alias);
    }

    /**
     * Why a type can't be typed with an alias kept for PHPStan, for an error that names it.
     */
    public static function phpStanOnlyReason(string $alias, string $class): string
    {
        return \sprintf('"%s" is #[PhpStanOnly] on %s: it is never emitted, so nothing sent can be typed with it.', $alias, $class);
    }
}
