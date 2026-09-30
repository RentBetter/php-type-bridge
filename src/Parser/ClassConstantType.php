<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

/**
 * A class constant, or a set of them: `self::STATUS_*`, `Foo::BAR`, `value-of<self::MODE_*>`.
 *
 * Only a placeholder — which constants match, and so which literal values the type allows,
 * depends on the class that owns the shape, which the parser does not know.
 * {@see \PTGS\TypeBridge\Support\PhpDocTypeHelper::resolveImportedNames()} replaces it with
 * those values.
 */
final readonly class ClassConstantType extends ParsedType
{
    public function __construct(
        public string $class,
        public string $pattern,
        public bool $valueOf = false,
    ) {}
}
