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
use PHPStan\Type\Accessory\NonEmptyArrayType;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\ErrorType;
use PHPStan\Type\Generic\TemplateType;
use PHPStan\Type\LateResolvableType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Reads a shape that extends another — `BaseData & array{extra: string}` — as TypeBridge emits it,
 * `interface … extends BaseData`: one shape holding the keys of both.
 *
 * PHPStan reads the intersection of two shapes as a set of values, which is never what a
 * projection extending a base means: two closed shapes that share no required key give `never`,
 * and one whose keys are all optional drops them. This reads the form TypeBridge parses, and only
 * that: a type alias naming one closed shape, `&`, then a keyed `array{…}`. As in an interface
 * extending another, a key the base declares may be declared again only to narrow it; any other
 * redeclaration is an invalid type, since the interface the emitter writes for it would not
 * compile. Every other intersection, an open base among them, is PHPStan's own reading.
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
        if (!$baseNode instanceof IdentifierTypeNode || !self::isKeyedShape($extensionNode)) {
            return null;
        }

        $baseType = $this->typeNodeResolver->resolve($baseNode, $nameScope);
        $extensionType = $this->typeNodeResolver->resolve($extensionNode, $nameScope);
        $base = self::shape($baseType);
        $extension = self::shape($extensionType);
        if (null === $base || null === $extension || $base->isUnsealed()->yes()) {
            // What PHPStan makes of the intersection itself, from the parts already resolved
            return TypeCombinator::intersect($baseType, $extensionType);
        }

        // Each key once, in the base's order and then the extension's, so a narrowed key is set to
        // the narrower type rather than merged with the one it replaces
        $extensionKeys = [];
        foreach ($extension->getKeyTypes() as $i => $keyType) {
            $extensionKeys[$keyType->getValue()] = $i;
        }

        // As wide as PHPStan reads a shape written out in full: degraded, it would lose every key
        $builder = ConstantArrayTypeBuilder::createEmpty();
        $builder->disableArrayDegradation();
        foreach ($base->getKeyTypes() as $i => $keyType) {
            $valueType = $base->getValueTypes()[$i];
            $optional = $base->isOptionalKey($i);

            $key = $keyType->getValue();
            if (isset($extensionKeys[$key])) {
                $j = $extensionKeys[$key];
                unset($extensionKeys[$key]);

                $narrowedType = $extension->getValueTypes()[$j];
                $narrowedOptional = $extension->isOptionalKey($j);
                if (!$valueType->isSuperTypeOf($narrowedType)->yes() || ($narrowedOptional && !$optional)) {
                    return new ErrorType(\sprintf(
                        "Key '%s' of the base shape is declared again by the shape extending it, which may only narrow it: to a narrower type, or required in place of optional.",
                        $key,
                    ));
                }
                [$valueType, $optional] = [$narrowedType, $narrowedOptional];
            }

            $builder->setOffsetValueType($keyType, $valueType, $optional);
        }
        foreach ($extensionKeys as $j) {
            $builder->setOffsetValueType($extension->getKeyTypes()[$j], $extension->getValueTypes()[$j], $extension->isOptionalKey($j));
        }

        $unsealed = $extension->getUnsealedTypes();
        if ($extension->isUnsealed()->yes() && null !== $unsealed) {
            $builder->makeUnsealed(...$unsealed);
        }

        $merged = $builder->getArray();

        // A non-empty-array{…} base keeps its promise when every key is optional
        if ($baseType->isIterableAtLeastOnce()->yes() && !$merged->isIterableAtLeastOnce()->yes()) {
            return TypeCombinator::intersect($merged, new NonEmptyArrayType());
        }

        return $merged;
    }

    private static function isKeyedShape(TypeNode $node): bool
    {
        if (!$node instanceof ArrayShapeNode || ArrayShapeNode::KIND_ARRAY !== $node->kind) {
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
     * The one shape a type is, or null: a union is not one, and neither is a template parameter
     * a shape bounds nor a shape keyed by one, which merging would resolve to the bound.
     */
    private static function shape(Type $type): ?ConstantArrayType
    {
        if ($type instanceof TemplateType || ($type instanceof LateResolvableType && !$type->isResolvable())) {
            return null;
        }

        $shapes = $type->getConstantArrays();
        if (1 !== \count($shapes) || !$type->isConstantArray()->yes()) {
            return null;
        }

        return $shapes[0];
    }
}
