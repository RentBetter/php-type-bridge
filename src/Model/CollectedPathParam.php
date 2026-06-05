<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

/**
 * A single route-derived path parameter: the placeholder name and its resolved TS type.
 * The raw route requirement is retained so later versions can attach a Zod schema or a
 * value-resolver target keyed off the same {@see \PTGS\TypeBridge\Routing\PathParam} pattern,
 * without changing this object's consumers.
 */
final readonly class CollectedPathParam
{
    public function __construct(
        public string $name,
        public string $tsType,
        public ?string $requirement = null,
    ) {}
}
