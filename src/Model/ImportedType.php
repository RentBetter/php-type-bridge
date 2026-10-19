<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

use PTGS\TypeBridge\Parser\ParsedType;
use PTGS\TypeBridge\Parser\ParsedTypes;

final readonly class ImportedType
{
    /**
     * @param string|null $sourceAlias the alias as the target declares it — `_self`, or a named
     *        alias that may itself be one the target imports — where the import says so; where it
     *        names none, the one it is imported as ({@see self::sourceName()})
     */
    public function __construct(
        public string $localAlias,
        public string $targetClass,
        public string $targetTypeName,
        public string $targetDomain,
        public ?string $sourceAlias = null,
    ) {}

    /**
     * The name the type is declared under where it comes from: the one the import names, or, where
     * it names none, the one it is imported as — `@phpstan-import-type Foo from Bar` is Bar's `Foo`.
     */
    public function sourceName(): string
    {
        return $this->sourceAlias ?? $this->localAlias;
    }

    /**
     * The imports the types use: those whose name they use as a type. A file imports for its own
     * code as well as for what it publishes, so an import none of them names is none of theirs.
     *
     * @param list<self> $imports
     *
     * @return list<self>
     */
    public static function usedIn(array $imports, ParsedType ...$types): array
    {
        $names = ParsedTypes::names(...$types);

        return array_values(array_filter($imports, static fn (self $import): bool => isset($names[$import->localAlias])));
    }
}
