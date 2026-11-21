<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

final readonly class NameRefType extends ParsedType
{
    /**
     * @param string $name e.g. 'IProjectBase' — an alias, or a class as it is written: its short name
     *        where it is used bare, in full where it is written in full or typed natively, once the
     *        names are resolved ({@see \PTGS\TypeBridge\Support\PhpDocTypeHelper::resolveImportedNames()}),
     *        so that no alias of its short name answers to it
     * @param ?string $class the class the name resolves to where it was written, if it is one.
     *        A shape can name a class for the JSON that class serialises to (`total: MoneyInterface`);
     *        the emitter falls back to that class's own type only when no alias has the name.
     */
    public function __construct(
        public string $name,
        public ?string $class = null,
    ) {}
}
