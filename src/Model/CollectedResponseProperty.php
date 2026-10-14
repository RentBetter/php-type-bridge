<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

use PTGS\TypeBridge\Parser\ParsedType;

final readonly class CollectedResponseProperty
{
    /**
     * @param list<ImportedType>|null $imports what its annotation was read against when that is not
     *        the response's own file: an inherited property is typed as the file declaring it says,
     *        so it is converted in that file's scope. Null for the response's own properties.
     */
    public function __construct(
        public string $name,
        public string $rawType,
        public ParsedType $parsed,
        public bool $optional = false,
        public ?array $imports = null,
    ) {}
}
