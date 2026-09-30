<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

/**
 * A generic TypeBridge has no built-in meaning for — `included<T>`, say, which a project's own
 * PHPStan extension reads. It emits as its type argument when config lists it among
 * `wrapperTypes`, and is an error otherwise.
 */
final readonly class GenericType extends ParsedType
{
    /**
     * @param list<ParsedType> $arguments
     */
    public function __construct(
        public string $name,
        public array $arguments,
    ) {}
}
