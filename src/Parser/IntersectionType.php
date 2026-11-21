<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Parser;

/**
 * A shape extending another (`Base & array{…}`). Its base is a name as written; a generic base's
 * template param in its place is what that param is bound to.
 */
final readonly class IntersectionType extends ParsedType
{
    public function __construct(
        public ParsedType $base,
        public ShapeType $extra,
    ) {}
}
