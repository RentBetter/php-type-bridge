<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

use PTGS\TypeBridge\Parser\ParsedType;

final readonly class CollectedResponseProperty
{
    /**
     * @param list<ImportedType> $imports what its annotation was read against: the imports and aliases
     *        of the file it is written in, so it is converted in that file's scope
     * @param string|null $writtenIn the class or trait whose file it is written in, when that is not
     *        the response's own — a base class, a trait
     * @param string|null $writtenInDomain the module that file is in, when it is written elsewhere:
     *        a bare name in it is that module's alias, as it would be in the module's own files,
     *        whichever module the response is emitted in
     */
    public function __construct(
        public string $name,
        public string $rawType,
        public ParsedType $parsed,
        public array $imports,
        public bool $optional = false,
        public ?string $writtenIn = null,
        public ?string $writtenInDomain = null,
    ) {}
}
