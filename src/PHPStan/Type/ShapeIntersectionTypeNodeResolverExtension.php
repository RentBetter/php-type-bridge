<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Type;

use PHPStan\Analyser\NameScope;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\PhpDoc\TypeNodeResolverAwareExtension;
use PHPStan\PhpDoc\TypeNodeResolverExtension;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\ErrorType;
use PHPStan\Type\Generic\TemplateType;
use PHPStan\Type\Type;

/**
 * Reads a shape that extends another — `BaseData & array{extra: string}` — as TypeBridge emits it,
 * `interface … extends BaseData`: one shape holding the keys of both.
 *
 * PHPStan reads the intersection of two closed shapes as a set of values, which is never what a
 * projection extending a base means: two shapes that share no required key give `never`, and one
 * whose keys are all optional drops them. This reads the form TypeBridge parses, and only that: a
 * type alias naming one closed shape, `&`, then a closed, keyed `array{…}`. A key both declare is
 * an error, as the emitter cannot write it. Any other intersection, an open shape among them, is
 * PHPStan's to resolve as it always has.
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
        if (!$typeNode instanceof IntersectionTypeNode || 2 !== \count($typeNode->types)) {
            return null;
        }

        [$baseNode, $extensionNode] = $typeNode->types;
        if (!$baseNode instanceof IdentifierTypeNode || !self::isClosedKeyedShape($extensionNode)) {
            return null;
        }

        $base = $this->closedShape($this->typeNodeResolver->resolve($baseNode, $nameScope));
        $extension = $this->closedShape($this->typeNodeResolver->resolve($extensionNode, $nameScope));
        if (null === $base || null === $extension) {
            return null;
        }

        // As wide as PHPStan reads a shape written out in full: degraded, it would lose every key
        $builder = ConstantArrayTypeBuilder::createEmpty();
        $builder->disableArrayDegradation();
        $seen = [];
        foreach ([$base, $extension] as $shape) {
            foreach ($shape->getKeyTypes() as $i => $keyType) {
                $key = $keyType->getValue();
                if (isset($seen[$key])) {
                    return new ErrorType();
                }
                $seen[$key] = true;
                $builder->setOffsetValueType($keyType, $shape->getValueTypes()[$i], $shape->isOptionalKey($i));
            }
        }

        return $builder->getArray();
    }

    private static function isClosedKeyedShape(TypeNode $node): bool
    {
        if (!$node instanceof ArrayShapeNode || ArrayShapeNode::KIND_ARRAY !== $node->kind || !$node->sealed) {
            return false;
        }

        foreach ($node->items as $item) {
            if (null === $item->keyName) {
                return false;
            }
        }

        return true;
    }

    /**
     * The one closed shape a type is, or null: a union, an open shape or a template parameter
     * bound by a shape is not one to merge into.
     */
    private function closedShape(Type $type): ?ConstantArrayType
    {
        if ($type instanceof TemplateType) {
            return null;
        }

        $shapes = $type->getConstantArrays();
        if (1 !== \count($shapes) || !$type->isConstantArray()->yes() || $shapes[0]->isUnsealed()->yes()) {
            return null;
        }

        return $shapes[0];
    }
}
