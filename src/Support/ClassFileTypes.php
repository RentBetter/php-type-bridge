<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

use PHPStan\PhpDocParser\Ast\PhpDoc\TypeAliasImportTagValueNode;
use PTGS\TypeBridge\Attribute\PhpStanOnly;
use PTGS\TypeBridge\Model\ImportedType;

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

    /** @var array<string, array<string, string>> by source directory: class => file */
    private array $classes = [];

    /** @var array<string, array{imports: array<string, ImportedType>, unresolved: array<string, string>, phpStanOnly: array<string, string>}> by source directory and class */
    private array $imports = [];

    /** @var array<string, array<string, list<string>>> by source directory */
    private array $shortNameMaps = [];

    public function __construct(
        private readonly PhpDocTypeHelper $docHelper = new PhpDocTypeHelper(),
        private readonly DomainGuesser $domainGuesser = new DomainGuesser(),
        private readonly PhpFileClassLocator $classLocator = new PhpFileClassLocator(),
    ) {}

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
     */
    public function loadable(string $class): bool
    {
        return $this->loadable[$class] ??= $this->classLocator->isLoadable($class);
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
     * The types the class imports, by the name it imports each as. `unresolved` is those whose
     * target can't be found or can't be loaded in this install, and `phpStanOnly` those of an alias
     * kept for PHPStan — still among `imports`, for what only reads its keys — each with why it
     * can't be emitted.
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

        $imports = [];
        $unresolved = $read['unresolved'];
        $phpStanOnly = [];
        foreach ($read['imports'] as $alias => $import) {
            if (!$this->loadable($import->targetClass)) {
                $unresolved[$alias] = self::unloadableReason($import->sourceName(), $import->targetClass);

                continue;
            }
            $imports[$alias] = $import;
            if (PhpStanOnly::of($import->targetClass)?->covers($import->sourceName()) ?? false) {
                $phpStanOnly[$alias] = self::phpStanOnlyReason($import->sourceName(), $import->targetClass);
            }
        }

        return $this->imports[$key] = ['imports' => $imports, 'unresolved' => $unresolved, 'phpStanOnly' => $phpStanOnly];
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
