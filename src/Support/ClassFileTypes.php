<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

use PTGS\TypeBridge\Attribute\PhpStanOnly;
use PTGS\TypeBridge\Model\ImportedType;

/**
 * What a class's file says its types mean — its source, the aliases it declares, the types it
 * imports, the classes it `use`s — read once per file however many collectors, properties and
 * queries ask, and read the same way for each of them.
 *
 * A file may import anything for its own code, so an import that can't be followed is set aside
 * with why, and fails only what is typed with it ({@see PhpDocTypeHelper::unresolvedImportUsedIn()}):
 * one whose target is outside the source tree, and one of an alias its class keeps for PHPStan
 * (#[PhpStanOnly]), which is never emitted however many other classes emit a type of that name.
 */
final class ClassFileTypes
{
    /** @var array<string, string|false> by file */
    private array $contents = [];

    /** @var array<string, array<string, string>> by file */
    private array $definitions = [];

    /** @var array<string, array<string, string>> by file */
    private array $classImports = [];

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
     * The file's source; null where it can't be read.
     */
    public function content(string $file): ?string
    {
        $content = $this->contents[$file] ??= is_file($file) ? file_get_contents($file) : false;

        return false === $content ? null : $content;
    }

    /**
     * Every alias the file declares, #[PhpStanOnly] ones included: alias => definition.
     *
     * @return array<string, string>
     */
    public function definitions(string $file): array
    {
        return $this->definitions[$file] ??= $this->docHelper->extractPhpStanTypes($this->content($file) ?? '');
    }

    /**
     * The file's `use` statements for classes, as alias => fully qualified name.
     *
     * @return array<string, string>
     */
    public function classImports(string $file): array
    {
        return $this->classImports[$file] ??= $this->docHelper->classImports($this->content($file) ?? '');
    }

    /**
     * The types the class imports, by the name it imports each as. `unresolved` is those whose
     * target can't be found, and `phpStanOnly` those of an alias kept for PHPStan — still among
     * `imports`, for what only reads its keys — each with why it can't be emitted.
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

        $read = $this->docHelper->extractImportedTypesLeniently(
            $this->content($file) ?? '',
            $class,
            $srcDir,
            $classFiles,
            $this->shortNameMaps[$srcDir] ??= $this->classLocator->shortNameMap(array_keys($classFiles)),
            $this->domainGuesser,
        );

        $phpStanOnly = [];
        foreach ($read['imports'] as $alias => $import) {
            $sourceAlias = $import->sourceAlias ?? $alias;
            if (PhpStanOnly::of($import->targetClass)?->covers($sourceAlias) ?? false) {
                $phpStanOnly[$alias] = self::phpStanOnlyReason($sourceAlias, $import->targetClass);
            }
        }

        return $this->imports[$key] = [...$read, 'phpStanOnly' => $phpStanOnly];
    }

    /**
     * Why a type can't be typed with an alias kept for PHPStan, for an error that names it.
     */
    public static function phpStanOnlyReason(string $alias, string $class): string
    {
        return \sprintf('"%s" is #[PhpStanOnly] on %s: it is never emitted, so nothing sent can be typed with it.', $alias, $class);
    }
}
