<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

use PTGS\TypeBridge\Parser\ParsedType;

final readonly class CollectedResponseProperty
{
    /**
     * @param list<ImportedType>|null $imports what its annotation was read against: the imports and
     *        aliases of the file it is written in, so it is converted in that file's scope. Null only
     *        for a property built without them, which is read against the response's imports.
     * @param string|null $writtenIn the class or trait whose file it is written in, when that is not
     *        the response's own — a base class, a trait. Such a scope is that file's alone: a name it
     *        writes for a class means the class, whatever the emitting module declares under it.
     */
    public function __construct(
        public string $name,
        public string $rawType,
        public ParsedType $parsed,
        public bool $optional = false,
        public ?array $imports = null,
        public ?string $writtenIn = null,
    ) {}
}
