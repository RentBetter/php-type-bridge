<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\PHPStan\Rules\Shape;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\ParsedType;
use PTGS\TypeBridge\Parser\ScalarType;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Support\PhpDocShapeParserResolver;

/**
 * Flags fields in TypeBridge _self shapes whose names end with `Id` and carry a string value.
 *
 * Reference fields should be named after the entity (singular): `project: string` not
 * `projectId: string`. The value being a UUID is implied by the name.
 *
 * Configurable allowlist covers external-system identifiers (e.g. `stripeCustomerId`)
 * that legitimately keep the Id suffix because they aren't UUIDs in our system.
 *
 * An entry is either a bare field name, which exempts that name in every shape, or a
 * `ShapeName.fieldName` pair, which exempts it only there — matching how `preserveNull` is
 * written. Prefer the qualified form: `stripeCustomerId` is an external identifier wherever it
 * appears, but a name that is only defensible on one shape should not be quietly permitted on
 * all of them. The shape name is the class's short name, as it is for preserveNull.
 *
 * @implements Rule<Class_>
 */
final class NoIdSuffixRule implements Rule
{
    /**
     * @param list<string> $allowIdSuffix field names that may legitimately keep the `Id`
     *                                    suffix, each either `fieldName` (anywhere) or
     *                                    `ShapeName.fieldName` (that shape only)
     */
    public function __construct(
        private readonly array $allowIdSuffix = [],
        private readonly PhpDocShapeParserResolver $resolver = new PhpDocShapeParserResolver(),
    ) {}

    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $shape = $this->resolver->resolveSelfShape($node);
        if (null === $shape) {
            return [];
        }

        $errors = [];
        $className = $node->namespacedName?->toString() ?? $scope->getClassReflection()?->getName() ?? ($node->name->name ?? '<anonymous>');

        $shapeName = self::shortName($className);

        foreach ($shape->fields as $field) {
            if (!$this->isOffendingField($field, $shapeName)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'Field `%s` in `_self` shape on %s must not end with `Id`. '
                    . 'Reference fields should be named after the entity (singular): use `%s` instead. '
                    . 'Add `%s.%s` to `typeBridge.shapeNaming.allowIdSuffix` if this is an external-system identifier, '
                    . 'or the bare `%s` if the name is an external identifier in every shape.',
                $field->name,
                $className,
                substr($field->name, 0, -2),
                $shapeName,
                $field->name,
                $field->name,
            ))
                ->identifier('typeBridge.shapeNaming.noIdSuffix')
                ->line($node->getStartLine())
                ->build();
        }

        return $errors;
    }

    private function isOffendingField(ShapeField $field, string $shapeName): bool
    {
        if (!str_ends_with($field->name, 'Id')) {
            return false;
        }

        if (\in_array($field->name, $this->allowIdSuffix, strict: true)) {
            return false;
        }

        if (\in_array($shapeName . '.' . $field->name, $this->allowIdSuffix, strict: true)) {
            return false;
        }

        return $this->isStringValued($field->type);
    }

    private static function shortName(string $className): string
    {
        $position = strrpos($className, '\\');

        return false === $position ? $className : substr($className, $position + 1);
    }

    private function isStringValued(ParsedType $type): bool
    {
        if ($type instanceof NullableType) {
            return $this->isStringValued($type->inner);
        }

        return $type instanceof ScalarType && 'string' === $type->type;
    }
}
