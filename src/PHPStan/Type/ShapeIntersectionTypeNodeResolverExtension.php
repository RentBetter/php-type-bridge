<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Type;

use PHPStan\Analyser\NameScope;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\PhpDoc\TypeNodeResolverAwareExtension;
use PHPStan\PhpDoc\TypeNodeResolverExtension;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\NeverType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Reads a shape that extends another — `BaseData & array{extra: string}` — as TypeBridge emits it,
 * `interface … extends BaseData`: one shape holding the keys of both.
 *
 * PHPStan reads the intersection of two closed shapes as one nothing satisfies, since a value of
 * the first has no key the first does not list. That leaves a projection extending a base (an
 * admin view adding to the shape a customer sees) unwritable without repeating the base's keys.
 * This steps in only there: when every part of the intersection is a single shape, no key is in
 * two of them, and PHPStan's own reading is `never`. Anything else — an unsealed shape it already
 * reads, a key two parts type differently — is PHPStan's to resolve as it always has.
 */
final class ShapeIntersectionTypeNodeResolverExtension implements TypeNodeResolverExtension, TypeNodeResolverAwareExtension
{
    private TypeNodeResolver $typeNodeResolver;

    public function setTypeNodeResolver(TypeNodeResolver $typeNodeResolver): void
    {
        $this->typeNodeResolver = $typeNodeResolver;
    }

    public function resolve(TypeNode $typeNode, NameScope $nameScope): ?Type
    {
        if (!$typeNode instanceof IntersectionTypeNode) {
            return null;
        }

        $shapes = [];
        foreach ($typeNode->types as $part) {
            $constantArrays = $this->typeNodeResolver->resolve($part, $nameScope)->getConstantArrays();
            if (1 !== \count($constantArrays)) {
                return null;
            }
            $shapes[] = $constantArrays[0];
        }

        if (!TypeCombinator::intersect(...$shapes) instanceof NeverType) {
            return null;
        }

        $builder = ConstantArrayTypeBuilder::createEmpty();
        $seen = [];
        foreach ($shapes as $shape) {
            foreach ($shape->getKeyTypes() as $i => $keyType) {
                $key = $keyType->getValue();
                if (isset($seen[$key])) {
                    return null;
                }
                $seen[$key] = true;
                $builder->setOffsetValueType($keyType, $shape->getValueTypes()[$i], $shape->isOptionalKey($i));
            }
        }

        return $builder->getArray();
    }
}
