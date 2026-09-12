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
 * The allowlist covers identifiers that legitimately keep the suffix — external-system ids
 * that are not UUIDs of ours, and correlation tokens with no entity to be named after.
 *
 * **Every entry must be qualified**, in one of two forms:
 *
 *   `ShapeName.fieldName`  exempt on that shape only. The shape name is the class's short
 *                          name, as it is for `preserveNull`.
 *   `_global.fieldName`    exempt in every shape.
 *
 * A bare `fieldName` is not accepted. It used to be, and it meant the second of those — so
 * exempting one field on one view quietly permitted that name across the whole project, which
 * is not what anyone writing the entry was asking for. Being broad is defensible; being broad
 * by accident is not, so it now has to be said out loud.
 *
 * @implements Rule<Class_>
 */
final class NoIdSuffixRule implements Rule
{
    /** Prefix that opts a field name out in every shape. */
    private const GLOBAL_SCOPE = '_global';

    /**
     * @param list<string> $allowIdSuffix each entry `ShapeName.fieldName` or
     *                                    `_global.fieldName`; bare field names do not match
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
                    . 'If this is an external-system identifier, add `%s.%s` to '
                    . '`typeBridge.shapeNaming.allowIdSuffix`, or `%s.%s` if the name is one in every shape.%s',
                $field->name,
                $className,
                substr($field->name, 0, -2),
                $shapeName,
                $field->name,
                self::GLOBAL_SCOPE,
                $field->name,
                $this->bareEntryHint($field->name),
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

        if (\in_array($shapeName . '.' . $field->name, $this->allowIdSuffix, strict: true)) {
            return false;
        }

        if (\in_array(self::GLOBAL_SCOPE . '.' . $field->name, $this->allowIdSuffix, strict: true)) {
            return false;
        }

        return $this->isStringValued($field->type);
    }

    /**
     * Said only when it explains the error in front of you: the config does name this field,
     * in the old unqualified form, and that quietly stopped matching.
     */
    private function bareEntryHint(string $fieldName): string
    {
        if (!\in_array($fieldName, $this->allowIdSuffix, strict: true)) {
            return '';
        }

        return \sprintf(
            ' (`%s` is currently listed unqualified, which no longer matches — a bare entry '
            . 'exempted the name in every shape, which was rarely what it was written for.)',
            $fieldName,
        );
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
