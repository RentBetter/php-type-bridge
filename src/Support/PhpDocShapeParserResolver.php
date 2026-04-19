<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Support;

use PhpParser\Node\Stmt\Class_;
use PTGS\TypeBridge\Parser\IntersectionType;
use PTGS\TypeBridge\Parser\PhpDocShapeParser;
use PTGS\TypeBridge\Parser\ShapeType;
use Throwable;

/**
 * Resolves the `@phpstan-type _self = array{...}` alias declared on a class docblock
 * and returns the field list of the backing shape.
 */
final class PhpDocShapeParserResolver
{
    public function __construct(
        private readonly PhpDocShapeParser $parser = new PhpDocShapeParser(),
        private readonly PhpDocTypeHelper $docHelper = new PhpDocTypeHelper(),
    ) {}

    public function resolveSelfShape(Class_ $class): ?ShapeType
    {
        $docComment = $class->getDocComment();
        if (null === $docComment) {
            return null;
        }

        $definitions = $this->docHelper->extractPhpStanTypes($docComment->getText());
        if (!isset($definitions['_self'])) {
            return null;
        }

        try {
            $parsed = $this->parser->parse($definitions['_self']);
        } catch (Throwable) {
            return null;
        }

        if ($parsed instanceof ShapeType) {
            return $parsed;
        }

        if ($parsed instanceof IntersectionType) {
            return $parsed->extra;
        }

        return null;
    }
}
