<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter\Builtin;

use PTGS\TypeBridge\Attribute\AsTypeBridgeEmitter;
use PTGS\TypeBridge\Emitter\ConversionScope;
use PTGS\TypeBridge\Emitter\EmitContext;
use PTGS\TypeBridge\Emitter\EmittedBlock;
use PTGS\TypeBridge\Emitter\EmittedType;
use PTGS\TypeBridge\Emitter\EmitMode;
use PTGS\TypeBridge\Emitter\TypeEmitter;
use PTGS\TypeBridge\Model\CollectedType;
use PTGS\TypeBridge\Parser\IntersectionType;
use PTGS\TypeBridge\Parser\NullableType;
use PTGS\TypeBridge\Parser\ShapeField;
use PTGS\TypeBridge\Parser\ShapeType;
use ReflectionClass;
use RuntimeException;

/**
 * Built-in convention: a class declaring a `@phpstan-type` (the `_self` alias)
 * emits as an interface (shape / intersection) or a type alias. Referenced —
 * emitted when collected.
 */
#[AsTypeBridgeEmitter('_self', mode: EmitMode::Referenced)]
final class SelfShapeEmitter implements TypeEmitter
{
    public function claims(ReflectionClass $class): bool
    {
        $docComment = $class->getDocComment();

        return false !== $docComment && str_contains($docComment, '@phpstan-type');
    }

    public function emit(ReflectionClass $class, EmitContext $context): EmittedType
    {
        $blocks = [];
        foreach ($context->collectedTypesFor($class->getName()) as $type) {
            $blocks[] = new EmittedBlock(20, '// Shapes', $this->renderType($type, $context));
        }

        return new EmittedType($context->domain, $blocks);
    }

    private function renderType(CollectedType $type, EmitContext $context): string
    {
        $scope = $context->scopeFor($type->imports);
        $name = $context->names->typeDeclarationName($type);

        if ($type->parsed instanceof IntersectionType) {
            $lines = [\sprintf('export interface %s extends %s {', $name, $context->convert($type->parsed->base, $scope))];
            foreach ($type->parsed->extra->fields as $field) {
                $lines[] = $this->renderShapeField($field, $type->name, $context, $scope);
            }
            $lines[] = '}';

            return implode("\n", $lines);
        }

        if ($type->parsed instanceof ShapeType) {
            $lines = [\sprintf('export interface %s {', $name)];
            foreach ($type->parsed->fields as $field) {
                $lines[] = $this->renderShapeField($field, $type->name, $context, $scope);
            }
            $lines[] = '}';

            return implode("\n", $lines);
        }

        return \sprintf('export type %s = %s;', $name, $context->convert($type->parsed, $scope));
    }

    private function renderShapeField(ShapeField $field, string $shapeName, EmitContext $context, ConversionScope $scope): string
    {
        $this->assertNullableMatchesPreserveNullConfig($field, $shapeName, $context);

        $optional = $field->optional;
        $type = $field->type;
        if ($type instanceof NullableType && $type->optional) {
            $optional = true;
            $type = $type->inner;
        }

        return \sprintf('  %s%s: %s;', $field->name, $optional ? '?' : '', $context->convert($type, $scope));
    }

    /**
     * Enforces the `preserveNull` invariant: fields listed in `preserveNull` must use
     * `T|null` annotations (emit as `field: T | null`); all other nullable fields must
     * use `?T` annotations (emit as `field?: T`). Mismatches throw at emit time so
     * codegen never silently drifts from policy.
     */
    private function assertNullableMatchesPreserveNullConfig(ShapeField $field, string $shapeName, EmitContext $context): void
    {
        if (!$field->type instanceof NullableType) {
            return;
        }

        $inPreserveNull = $context->isPreserveNull($shapeName, $field->name);

        if ($inPreserveNull && $field->type->optional) {
            throw new RuntimeException(\sprintf(
                'Field `%s` in shape `%s` is listed in preserveNull config but its '
                . '@phpstan-type annotation is `?T` (TS optional). Change the annotation '
                . 'to `T|null` so null is emitted on the wire, or remove `%s` from preserveNull.',
                $field->name,
                $shapeName,
                $shapeName . '.' . $field->name,
            ));
        }

        if (!$inPreserveNull && !$field->type->optional) {
            throw new RuntimeException(\sprintf(
                'Field `%s` in shape `%s` has `T|null` annotation but is not listed in '
                . 'preserveNull config. Change the annotation to `?T` (emits as `%s?: T` '
                . 'and the field should be omitted when null), or add `%s` to preserveNull '
                . 'if null is semantically meaningful for this field.',
                $field->name,
                $shapeName,
                $field->name,
                $shapeName . '.' . $field->name,
            ));
        }
    }
}
