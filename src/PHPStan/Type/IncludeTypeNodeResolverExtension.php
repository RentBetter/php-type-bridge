<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Type;

use PHPStan\Analyser\NameScope;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\PhpDoc\TypeNodeResolverAwareExtension;
use PHPStan\PhpDoc\TypeNodeResolverExtension;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Type\ErrorType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PTGS\TypeBridge\Http\Include\EnumCase;
use PTGS\TypeBridge\Http\Include\Optional;
use PTGS\TypeBridge\Http\Include\Ref;

/**
 * Teaches PHPStan the generics a shape writes for what IncludeResolver writes out, under the names
 * the app lists in `typeBridgeIncludes` (includes.neon) — the same names as `includes.types`,
 * `includes.refTypes` and `includes.enumTypes` in its TypeBridge config:
 *
 * - `included<T>` is `T|Optional<T>`: a normaliser returns the key as an Optional
 *   (IncludeMarkers::optional()), and the resolver turns it into T or drops the key before the
 *   body is written, so the one spelling is true of the array and of the wire alike;
 * - `ref<RecordData>` is a {@see Ref}. The record shape it names is for TypeBridge, which emits
 *   `Ref<RecordData>`; PHPStan does not check it against the entity the ref was made from;
 * - `enum<Status>` is an `EnumCase<Status>`, which is what IncludeMarkers::enum() returns.
 *   TypeBridge emits the enum's own type, which is what the resolver sends.
 */
final class IncludeTypeNodeResolverExtension implements TypeNodeResolverExtension, TypeNodeResolverAwareExtension
{
    private TypeNodeResolver $typeNodeResolver;

    /**
     * @param list<string> $types the `included<T>` generics
     * @param list<string> $refTypes the `ref<T>` generics
     * @param list<string> $enumTypes the `enum<E>` generics
     */
    public function __construct(
        private readonly array $types,
        private readonly array $refTypes,
        private readonly array $enumTypes,
    ) {}

    public function setTypeNodeResolver(TypeNodeResolver $typeNodeResolver): void
    {
        $this->typeNodeResolver = $typeNodeResolver;
    }

    public function resolve(TypeNode $typeNode, NameScope $nameScope): ?Type
    {
        if (!$typeNode instanceof GenericTypeNode) {
            return null;
        }

        $name = $typeNode->type->name;
        $included = \in_array($name, $this->types, true);
        $ref = \in_array($name, $this->refTypes, true);
        $enum = \in_array($name, $this->enumTypes, true);
        if (!$included && !$ref && !$enum) {
            return null;
        }

        if (1 !== \count($typeNode->genericTypes)) {
            return new ErrorType();
        }

        if ($ref) {
            return new ObjectType(Ref::class);
        }

        $inner = $this->typeNodeResolver->resolve($typeNode->genericTypes[0], $nameScope);

        return $enum
            ? new GenericObjectType(EnumCase::class, [$inner])
            : TypeCombinator::union($inner, new GenericObjectType(Optional::class, [$inner]));
    }
}
