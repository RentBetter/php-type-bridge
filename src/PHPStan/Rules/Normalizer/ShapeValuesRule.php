<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Rules\Normalizer;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;
use PHPStan\Type\TypeTraverser;
use PTGS\TypeBridge\Contract\ShapeNormalizer;
use PTGS\TypeBridge\Http\Include\EnumCase;
use PTGS\TypeBridge\Http\Include\Optional;
use PTGS\TypeBridge\Http\Include\Ref;
use PTGS\TypeBridge\Normalizer\IncludeMarkers;

/**
 * A shape written by a normaliser that uses {@see IncludeMarkers} holds scalars, lists and shapes
 * of them, and the markers the normaliser makes — enum(), ref(), optional() — nothing else. An
 * object in a shape goes out as whatever it serialises itself to, so what reaches the wire would
 * be decided somewhere other than the normaliser.
 *
 * The shapes are those the normaliser declares itself, and those declared on the class it names
 * as its `ShapeNormalizer<TSource, TShapeOwner>` owner — a view's `_self`. Checking the shapes is
 * enough: PHPStan already holds each normaliser to the shape it says it returns. A marker's own
 * type is not looked into — what it wraps is declared, and checked, where it is made.
 *
 * Registered by includes.neon, beside the extension that resolves `included<T>`, `ref<T>` and
 * `enum<E>`, which it needs to read those shapes at all.
 *
 * @implements Rule<InClassNode>
 */
final readonly class ShapeValuesRule implements Rule
{
    /** @var list<class-string> */
    private const array MARKERS = [EnumCase::class, Optional::class, Ref::class];

    public function __construct(
        private TypeNodeResolver $typeNodeResolver,
    ) {}

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        if (!$class->hasTraitUse(IncludeMarkers::class)) {
            return [];
        }

        $errors = [];
        foreach (self::shapeOwners($class) as $owner) {
            foreach ($owner->getResolvedPhpDoc()?->getTypeAliasTags() ?? [] as $name => $tag) {
                // @phpstan-ignore phpstanApi.method (resolve() is the one way from a type alias to its type)
                $objects = self::objectsIn($tag->getTypeAlias()->resolve($this->typeNodeResolver));
                if ([] !== $objects) {
                    $errors[] = self::error($name, $owner, $class, $objects);
                }
            }
        }

        return $errors;
    }

    /**
     * The normaliser itself, and the shape owner its ShapeNormalizer implementation names, when
     * that is another class.
     *
     * @return list<ClassReflection>
     */
    private static function shapeOwners(ClassReflection $class): array
    {
        $owners = [$class];
        $owner = $class->getAncestorWithClassName(ShapeNormalizer::class)?->getActiveTemplateTypeMap()->getType('TShapeOwner');
        foreach ($owner?->getObjectClassReflections() ?? [] as $reflection) {
            if ($reflection->getName() !== $class->getName()) {
                $owners[] = $reflection;
            }
        }

        return $owners;
    }

    /**
     * @param list<string> $objects
     */
    private static function error(string $name, ClassReflection $owner, ClassReflection $normalizer, array $objects): IdentifierRuleError
    {
        $where = $owner === $normalizer
            ? $owner->getDisplayName()
            : \sprintf('%s, which %s writes,', $owner->getDisplayName(), $normalizer->getDisplayName());

        return RuleErrorBuilder::message(\sprintf(
            'Shape %s on %s holds %s. A shape a normaliser writes with IncludeMarkers holds scalars, lists and shapes, and the markers it makes (enum(), ref(), optional()), so every decision about what goes out is made in the normaliser.',
            $name,
            $where,
            implode(', ', $objects),
        ))->identifier('typeBridge.shapeValue')->build();
    }

    /**
     * The objects a type holds, other than markers, at any depth.
     *
     * @return list<string>
     */
    private static function objectsIn(Type $type): array
    {
        $objects = [];
        TypeTraverser::map($type, static function (Type $type, callable $traverse) use (&$objects): Type {
            if (!$type->isObject()->yes()) {
                return $traverse($type);
            }

            $classes = $type->getObjectClassNames();
            foreach ([] === $classes ? ['object'] : $classes as $class) {
                if (!\in_array($class, self::MARKERS, true)) {
                    $objects[$class] = true;
                }
            }

            return $type;
        });

        return array_map(strval(...), array_keys($objects));
    }
}
