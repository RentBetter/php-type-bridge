<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

use PTGS\TypeBridge\Parser\ParsedType;

final readonly class CollectedResponseProperty
{
    /**
     * @param list<ImportedType>|null $imports what its annotation was read against: the imports and
     *        aliases of the file declaring it — the response's own, or a base class's — so it is
     *        converted in that file's scope. Null only for a property built without them, which is
     *        read against the response's imports.
     */
    public function __construct(
        public string $name,
        public string $rawType,
        public ParsedType $parsed,
        public bool $optional = false,
        public ?array $imports = null,
    ) {}
}
