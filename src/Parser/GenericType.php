<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

/**
 * A generic TypeBridge has no built-in meaning for — `included<T>`, say, which a project's own
 * PHPStan extension reads. Config's `includes.types` lists the ones that mark a key only sent
 * when a request asks for it; any other is an error at emission.
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
