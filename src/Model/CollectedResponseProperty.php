<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

use PTGS\TypeBridge\Parser\ParsedType;

final readonly class CollectedResponseProperty
{
    /**
     * @param list<ImportedType> $imports what its annotation was read against: the imports and aliases
     *        of the file it is written in, so it is converted in that file's scope. None, for a
     *        property read where nothing is imported
     * @param string|null $writtenIn the class or trait whose file it is written in, when that is not
     *        the response's own — a base class, a trait, a library's either
     * @param string|null $writtenInDomain the module that file is in, when it is another: a bare name
     *        in it is that module's alias, as it would be in the module's own files, whichever module
     *        the response is emitted in. Null for a library's file, which is in none: every name it
     *        writes is a class, resolved where it was read
     */
    public function __construct(
        public string $name,
        public string $rawType,
        public ParsedType $parsed,
        public bool $optional = false,
        public array $imports = [],
        public ?string $writtenIn = null,
        public ?string $writtenInDomain = null,
    ) {}
}
