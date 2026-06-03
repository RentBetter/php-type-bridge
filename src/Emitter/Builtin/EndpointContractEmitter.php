<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter\Builtin;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\AsTypeBridgeEmitter;
use PTGS\TypeBridge\Emitter\EmitContext;
use PTGS\TypeBridge\Emitter\EmittedBlock;
use PTGS\TypeBridge\Emitter\EmittedType;
use PTGS\TypeBridge\Emitter\EmitMode;
use PTGS\TypeBridge\Emitter\TypeEmitter;
use PTGS\TypeBridge\Model\CollectedApiResponseClass;
use PTGS\TypeBridge\Model\CollectedEndpointContract;
use ReflectionClass;
use ReflectionMethod;

/**
 * Built-in convention: a controller method carrying #[ApiResponses] emits its
 * request-input aliases (// Endpoint inputs) and a status-keyed result map
 * (// Endpoint results). The shared `EndpointResult<M>` helper is emitted once
 * per module by the orchestrator via {@see self::RESULT_HELPER}.
 */
#[AsTypeBridgeEmitter('endpoint-contracts', mode: EmitMode::Referenced)]
final class EndpointContractEmitter implements TypeEmitter
{
    public const string RESULT_HELPER = "export type EndpointResult<M extends Record<number, unknown>> = {\n"
        . "  [S in keyof M & number]: {\n"
        . "    ok: S extends 200 | 201 | 202 | 204 ? true : false;\n"
        . "    status: S;\n"
        . "    data: M[S];\n"
        . "  };\n"
        . '}[keyof M & number];';

    public function claims(ReflectionClass $class): bool
    {
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ([] !== $method->getAttributes(ApiResponses::class)) {
                return true;
            }
        }

        return false;
    }

    public function emit(ReflectionClass $class, EmitContext $context): EmittedType
    {
        $contracts = $context->contractsForController($class->getName());

        $blocks = [];
        foreach ($contracts as $contract) {
            if (null !== $contract->request && $contract->request->hasAnyInput()) {
                $blocks[] = new EmittedBlock(40, '// Endpoint inputs', $this->renderInputs($contract, $context));
            }
        }
        foreach ($contracts as $contract) {
            $blocks[] = new EmittedBlock(50, '// Endpoint results', $this->renderResult($contract, $context));
        }

        return new EmittedType($context->domain, $blocks);
    }

    private function renderInputs(CollectedEndpointContract $contract, EmitContext $context): string
    {
        $request = $contract->request;
        if (null === $request) {
            return '';
        }

        $lines = [];
        if (null !== $request->query) {
            $lines[] = \sprintf('export type %s = %s;', $context->naming->queryAliasName($contract->name), $context->symbolForInputReference($request->query));
        }
        if (null !== $request->body) {
            $lines[] = \sprintf('export type %s = %s;', $context->naming->bodyAliasName($contract->name), $context->symbolForInputReference($request->body));
        }
        if (null !== $request->path) {
            $lines[] = \sprintf('export type %s = %s;', $context->naming->pathAliasName($contract->name), $context->symbolForInputReference($request->path));
        }

        return implode("\n", $lines);
    }

    private function renderResult(CollectedEndpointContract $contract, EmitContext $context): string
    {
        $responses = $contract->responses;
        usort($responses, static fn(CollectedApiResponseClass $left, CollectedApiResponseClass $right): int => $left->status <=> $right->status);

        $lines = [\sprintf('export type %s = {', $context->naming->endpointMapName($contract->name))];
        foreach ($responses as $response) {
            $lines[] = \sprintf('  %d: %s;', $response->status, $context->responseSymbolName($response));
        }
        $lines[] = '};';
        $lines[] = \sprintf('export type %s = EndpointResult<%s>;', $context->naming->endpointResultName($contract->name), $context->naming->endpointMapName($contract->name));

        return implode("\n", $lines);
    }
}
