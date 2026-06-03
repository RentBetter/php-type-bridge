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
use PTGS\TypeBridge\Model\CollectedApiResponseClass;
use ReflectionClass;

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

        $lines = [\sprintf('export interface %s {', $context->names->responseDeclarationName($response))];
        foreach ($response->properties as $property) {
            $lines[] = \sprintf(
                '  %s%s: %s;',
                $property->name,
                $property->optional ? '?' : '',
                $context->convert($property->parsed, $scope),
            );
        }
        $lines[] = '}';

        return implode("\n", $lines);
    }
}
