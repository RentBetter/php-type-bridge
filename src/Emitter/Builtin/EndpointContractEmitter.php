<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter\Builtin;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\AsTypeBridgeEmitter;
use PTGS\TypeBridge\Config\IncludeConvention;
use PTGS\TypeBridge\Emitter\EmitContext;
use PTGS\TypeBridge\Emitter\EmittedBlock;
use PTGS\TypeBridge\Emitter\EmittedType;
use PTGS\TypeBridge\Emitter\EmitMode;
use PTGS\TypeBridge\Emitter\TypeEmitter;
use PTGS\TypeBridge\Model\CollectedApiResponseClass;
use PTGS\TypeBridge\Model\CollectedEndpointContract;
use PTGS\TypeBridge\Model\CollectedPathParam;
use ReflectionClass;
use ReflectionMethod;

/**
 * Built-in convention: a controller method carrying #[ApiResponses] emits its
 * request-input aliases (// Endpoint inputs), a status-keyed result map
 * (// Endpoint results), and a constant saying how to call it (// Endpoints): its method and
 * path, typed with that map and its inputs so a client's helpers can read them. The shared
 * `EndpointResult<M>` and `Endpoint<M, I>` helpers are declared by the orchestrator via
 * {@see self::RESULT_HELPER} and {@see self::ENDPOINT_HELPER}: once in the root module when
 * there is one, once per module otherwise.
 *
 * An endpoint whose success response has a body takes the include query parameters too
 * (`includes.query`): its query is `IncludeQuery`, or its own query form's type and that. The
 * orchestrator declares `IncludeQuery` the same way, via {@see self::includeQueryHelper()}.
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

    public const string ENDPOINT_HELPER = <<<'TS'
        /** How to call an endpoint. Its response map and inputs ride along in the type, for helpers that call it. */
        export interface Endpoint<M extends Record<number, unknown> = Record<number, unknown>, I = Record<never, never>> {
          readonly method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
          /** The route's path, `{placeholders}` and all. */
          readonly path: string;
          /** Never set at runtime: the types a helper reads off the endpoint. */
          readonly types?: { responses: M; input: I };
        }
        TS;

    private const array METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * @param list<CollectedEndpointContract> $contracts
     */
    public static function anyTakesIncludeQuery(array $contracts, IncludeConvention $includes): bool
    {
        foreach ($contracts as $contract) {
            if (self::takesIncludeQuery($contract, $includes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * `IncludeQuery`: each include query parameter as an optional string, described as configured.
     */
    public static function includeQueryHelper(IncludeConvention $includes): string
    {
        $lines = ['export interface IncludeQuery {'];
        foreach ($includes->query as $name => $description) {
            $lines[] = '  /** ' . str_replace('*/', '*\\/', $description) . ' */';
            $lines[] = "  {$name}?: string;";
        }
        $lines[] = '}';

        return implode("\n", $lines);
    }

    private static function takesIncludeQuery(CollectedEndpointContract $contract, IncludeConvention $includes): bool
    {
        return [] !== $includes->query && $contract->hasSuccessBody();
    }

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
            if ((null !== $contract->request && $contract->request->hasAnyInput()) || self::takesIncludeQuery($contract, $context->includes)) {
                $blocks[] = new EmittedBlock(40, '// Endpoint inputs', $this->renderInputs($contract, $context));
            }
        }
        foreach ($contracts as $contract) {
            $blocks[] = new EmittedBlock(50, '// Endpoint results', $this->renderResult($contract, $context));
        }
        foreach ($contracts as $contract) {
            $blocks[] = new EmittedBlock(60, '// Endpoints', $this->renderEndpoint($contract, $context), $contract->name);
        }

        return new EmittedType($context->domain, $blocks);
    }

    private function renderInputs(CollectedEndpointContract $contract, EmitContext $context): string
    {
        $lines = [];
        if (null !== $query = $this->queryType($contract, $context)) {
            $lines[] = \sprintf('export type %s = %s;', $context->naming->queryAliasName($contract->name), $query);
        }

        $request = $contract->request;
        if (null === $request) {
            return implode("\n", $lines);
        }

        if (null !== $request->body) {
            $lines[] = \sprintf('export type %s = %s;', $context->naming->bodyAliasName($contract->name), $context->symbolForInputReference($request->body));
        }
        if (null !== $request->path) {
            $lines[] = \sprintf('export type %s = %s;', $context->naming->pathAliasName($contract->name), $context->symbolForInputReference($request->path));
        } elseif (null !== $request->pathParams) {
            $lines[] = \sprintf('export type %s = %s;', $context->naming->pathAliasName($contract->name), $this->renderPathParams($request->pathParams));
        }

        return implode("\n", $lines);
    }

    /**
     * The endpoint's query: its query form's type, `IncludeQuery` when its response has a body to
     * shape, both intersected, or none.
     */
    private function queryType(CollectedEndpointContract $contract, EmitContext $context): ?string
    {
        $types = [];
        if (null !== $query = $contract->request?->query) {
            $types[] = $context->symbolForInputReference($query);
        }
        if (self::takesIncludeQuery($contract, $context->includes)) {
            $types[] = 'IncludeQuery';
        }

        return [] === $types ? null : implode(' & ', $types);
    }

    /**
     * Renders route-derived path params as an inline object type, e.g. `{ accountId: string }`.
     *
     * @param list<CollectedPathParam> $params
     */
    private function renderPathParams(array $params): string
    {
        $fields = [];
        foreach ($params as $param) {
            $fields[] = \sprintf('%s: %s', $param->name, $param->tsType);
        }

        return '{ ' . implode('; ', $fields) . ' }';
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

    /**
     * `export const ListProjects: Endpoint<ListProjectsEndpointMap, { query?: ListProjectsQuery }> = {…}`:
     * the method and path to call, typed with the endpoint's responses and the inputs it takes —
     * its path params and body when it has them, and its query, which a caller may leave out.
     */
    private function renderEndpoint(CollectedEndpointContract $contract, EmitContext $context): string
    {
        if (!\in_array($contract->httpMethod, self::METHODS, true)) {
            throw new \RuntimeException(\sprintf(
                'Endpoint "%s" is routed for %s, which an Endpoint cannot describe: it takes %s.',
                $contract->name,
                $contract->httpMethod,
                implode(', ', self::METHODS),
            ));
        }

        $inputs = [];
        $request = $contract->request;
        if (null !== $request && (null !== $request->path || null !== $request->pathParams)) {
            $inputs[] = 'path: ' . $context->naming->pathAliasName($contract->name);
        }
        if (null !== $this->queryType($contract, $context)) {
            $inputs[] = 'query?: ' . $context->naming->queryAliasName($contract->name);
        }
        if (null !== $request?->body) {
            $inputs[] = 'body: ' . $context->naming->bodyAliasName($contract->name);
        }

        $type = $context->naming->endpointMapName($contract->name);
        if ([] !== $inputs) {
            $type .= ', { ' . implode('; ', $inputs) . ' }';
        }

        return \sprintf(
            "export const %s: Endpoint<%s> = {\n  method: '%s',\n  path: '%s',\n};",
            $contract->name,
            $type,
            $contract->httpMethod,
            addcslashes($contract->httpPath, "'\\"),
        );
    }
}
