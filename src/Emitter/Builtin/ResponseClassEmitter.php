<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter\Builtin;

use PTGS\TypeBridge\Attribute\AsTypeBridgeEmitter;
use PTGS\TypeBridge\Contract\ApiResponse;
use PTGS\TypeBridge\Emitter\EmitContext;
use PTGS\TypeBridge\Emitter\EmittedBlock;
use PTGS\TypeBridge\Emitter\EmittedType;
use PTGS\TypeBridge\Emitter\EmitMode;
use PTGS\TypeBridge\Emitter\TypeEmitter;
use PTGS\TypeBridge\Emitter\TypeScriptEmitter;
use PTGS\TypeBridge\Model\CollectedApiResponseClass;
use ReflectionClass;
use ReflectionProperty;

/**
 * Built-in convention: a class implementing {@see ApiResponse} emits as a response
 * interface (or `= null` for an empty 204). Referenced — emitted when collected.
 */
#[AsTypeBridgeEmitter('responses', mode: EmitMode::Referenced)]
final class ResponseClassEmitter implements TypeEmitter
{
    public function claims(ReflectionClass $class): bool
    {
        return $class->implementsInterface(ApiResponse::class) && $class->isInstantiable();
    }

    public function emit(ReflectionClass $class, EmitContext $context): EmittedType
    {
        $response = $context->responseFor($class->getName());
        if (null === $response) {
            return new EmittedType($context->domain, []);
        }

        return new EmittedType($context->domain, [new EmittedBlock(30, '// Responses', $this->render($response, $context))]);
    }

    private function render(CollectedApiResponseClass $response, EmitContext $context): string
    {
        $scope = $context->scopeFor($response->imports);

        if (204 === $response->status && [] === $response->properties) {
            return \sprintf('export type %s = null;', $context->names->responseDeclarationName($response));
        }

        $name = $context->names->responseDeclarationName($response);
        $lines = [\sprintf('export interface %s {', $name)];
        $included = [];
        foreach ($response->properties as $property) {
            $isIncluded = $context->converter->isIncluded($property->parsed);
            $lines[] = \sprintf(
                '  %s%s: %s;',
                $property->name,
                $property->optional || $isIncluded ? '?' : '',
                $context->convert($property->parsed, $scope),
            );

            // Present whenever it is asked for: a side-load (an empty list when nothing is
            // referenced), or an included value whose PHP property is not nullable.
            if (self::isSideLoad($response->className, $property->name, $context) || ($isIncluded && !$property->optional)) {
                $included[] = "'" . $property->name . "'";
            }
        }
        $lines[] = '}';

        if ([] !== $included) {
            $lines[] = '';
            $lines[] = \sprintf('export type %s%s = %s;', $name, TypeScriptEmitter::INCLUDED_SUFFIX, implode(' | ', $included));
        }

        return implode("\n", $lines);
    }

    private static function isSideLoad(string $class, string $property, EmitContext $context): bool
    {
        $attribute = $context->converter->includes->sideLoadAttribute;
        if (null === $attribute || !class_exists($class) || !property_exists($class, $property)) {
            return false;
        }

        return [] !== (new ReflectionProperty($class, $property))->getAttributes($attribute);
    }
}
