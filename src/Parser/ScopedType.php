<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

use PTGS\TypeBridge\Model\ImportedType;

/**
 * A type written in another file than the type it sits in — what a generic base's template param
 * is bound to, put in place of the param in the base's property — read in that file's scope: the
 * types its file imports, and the module whose aliases its bare names are. A file outside the
 * source tree is in none, and spells its names as the classes they are.
 */
final readonly class ScopedType extends ParsedType
{
    /**
     * @param list<ImportedType> $imports the types the file it is written in imports, its own aliases among them
     */
    public function __construct(
        public ParsedType $inner,
        public array $imports,
        public ?string $domain,
    ) {}
}
