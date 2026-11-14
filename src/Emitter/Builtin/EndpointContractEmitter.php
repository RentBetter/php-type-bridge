<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Emitter\Builtin;

use PTGS\TypeBridge\Attribute\ApiResponses;
use PTGS\TypeBridge\Attribute\AsTypeBridgeEmitter;
use PTGS\TypeBridge\Config\IncludeConvention;
use PTGS\TypeBridge\Config\TypeScriptNaming;
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
 * An endpoint with a query form takes its type as a query string sends it, `AsQuery<T>`
 * ({@see self::AS_QUERY_HELPER}). One whose success response has a body takes the include query
 * parameters too (`includes.query`): its query is `IncludeQuery`, or its own query form's and that.
 * The orchestrator declares both the same way, `IncludeQuery` via {@see self::includeQueryHelper()}.
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

    /**
     * `AsQuery<T>`: T as a query string sends it. A query string carries no null, so a key that may
     * be null is sent by leaving it out, and only so — what it takes when sent is never null, or a
     * client would send the text "null" — at any depth, a shape it sends included. The type itself
     * stays as declared, for whatever else uses it — a body, a response — where a null is sent and
     * the key is always there.
     */
    public const string AS_QUERY_HELPER = <<<'TS'
        /** T as a query string sends it: a key that may be null may be left out, at any depth, since a query string sends a null by leaving the key out, and only so — and a list's item is never null. A primitive, a branded one included, is as it is, and a list or a tuple stays one. */
        export type AsQuery<T> = T extends string | number | boolean | bigint | symbol | null | undefined
          ? T
          : T extends readonly unknown[]
            ? { [K in keyof T]: AsQuery<Exclude<T[K], null>> }
            : T extends object
              ? { [K in keyof T as null extends T[K] ? K : never]?: AsQuery<Exclude<T[K], null>> } & { [K in keyof T as null extends T[K] ? never : K]: AsQuery<T[K]> }
              : T;
        TS;

    private const array METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * The helpers a module's endpoints share, each keyed by its name: declared in the module, or in
     * the root module and imported.
     *
     * @param list<CollectedEndpointContract> $contracts the module's
     *
     * @return list<EmittedBlock>
     */
    public static function helpers(array $contracts, IncludeConvention $includes): array
    {
        if ([] === $contracts) {
            return [];
        }

        return [
            ...(self::anyTakesQueryForm($contracts) ? [new EmittedBlock(40, '// Endpoint inputs', self::AS_QUERY_HELPER, 'AsQuery')] : []),
            ...(self::anyTakesIncludeQuery($contracts, $includes) ? [new EmittedBlock(40, '// Endpoint inputs', self::includeQueryHelper($includes), 'IncludeQuery')] : []),
            new EmittedBlock(50, '// Endpoint results', self::RESULT_HELPER, 'EndpointResult'),
            new EmittedBlock(60, '// Endpoints', self::ENDPOINT_HELPER, 'Endpoint'),
        ];
    }

    /**
     * The names of the helpers a module's endpoints share ({@see self::helpers()}).
     *
     * @param list<CollectedEndpointContract> $contracts the module's
     *
     * @return list<string>
     */
    public static function helperNames(array $contracts, IncludeConvention $includes): array
    {
        return array_map(static fn (EmittedBlock $helper): string => (string) $helper->sortKey, self::helpers($contracts, $includes));
    }

    /**
     * Every type name an endpoint declares of its own — its inputs, response map and result
     * ({@see self::declared()}) — each with what it names, to say which two clash. Beside the helpers
     * its module takes ({@see self::helperNames()}), all a module names for its endpoints.
     *
     * @return list<array{string, string}> the name, and what it names
     */
    public static function endpointNames(CollectedEndpointContract $contract, TypeScriptNaming $naming, IncludeConvention $includes): array
    {
        $what = ['query' => 'the query of', 'body' => 'the body of', 'path' => 'the path of', 'map' => 'the response map of', 'result' => 'the result of'];
        $names = [];
        foreach (self::declared($contract, $naming, $includes) as $part => $name) {
            $names[] = [$name, $what[$part] . ' endpoint ' . $contract->name];
        }

        return $names;
    }

    /**
     * The types an endpoint declares of its own, each under its name: its query — its query form's,
     * the include query, or both — its body and its path where it takes them, its response map and
     * its result. What {@see self::emit()} declares, and {@see self::endpointNames()} claims.
     *
     * @return array{query?: string, body?: string, path?: string, map: string, result: string}
     */
    private static function declared(CollectedEndpointContract $contract, TypeScriptNaming $naming, IncludeConvention $includes): array
    {
        $request = $contract->request;
        $names = [];
        if (null !== $request?->query || self::takesIncludeQuery($contract, $includes)) {
            $names['query'] = $naming->queryAliasName($contract->name);
        }
        if (null !== $request?->body) {
            $names['body'] = $naming->bodyAliasName($contract->name);
        }
        if (null !== $request?->path || null !== $request?->pathParams) {
            $names['path'] = $naming->pathAliasName($contract->name);
        }
        $names['map'] = $naming->endpointMapName($contract->name);
        $names['result'] = $naming->endpointResultName($contract->name);

        return $names;
    }

    /**
     * Whether any of the contracts takes a query form, whose type it takes as `AsQuery<T>`.
     *
     * @param list<CollectedEndpointContract> $contracts
     */
    public static function anyTakesQueryForm(array $contracts): bool
    {
        foreach ($contracts as $contract) {
            if (null !== $contract->request?->query) {
                return true;
            }
        }

        return false;
    }

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

        $declared = [];
        foreach ($contracts as $i => $contract) {
            $declared[$i] = self::declared($contract, $context->naming, $context->includes);
        }

        $blocks = [];
        foreach ($contracts as $i => $contract) {
            $inputs = $this->renderInputs($contract, $declared[$i], $context);
            if ('' !== $inputs) {
                $blocks[] = new EmittedBlock(40, '// Endpoint inputs', $inputs);
            }
        }
        foreach ($contracts as $i => $contract) {
            $blocks[] = new EmittedBlock(50, '// Endpoint results', $this->renderResult($contract, $declared[$i], $context));
        }
        foreach ($contracts as $i => $contract) {
            $blocks[] = new EmittedBlock(60, '// Endpoints', $this->renderEndpoint($contract, $declared[$i], $context), $contract->name);
        }

        return new EmittedType($context->domain, $blocks);
    }

    /**
     * @param array{query?: string, body?: string, path?: string, map: string, result: string} $declared
     */
    private function renderInputs(CollectedEndpointContract $contract, array $declared, EmitContext $context): string
    {
        $request = $contract->request;
        $lines = [];
        if (isset($declared['query'])) {
            $lines[] = \sprintf('export type %s = %s;', $declared['query'], $this->queryType($contract, $context));
        }
        if (isset($declared['body']) && null !== $request?->body) {
            $lines[] = \sprintf('export type %s = %s;', $declared['body'], $context->symbolForInputReference($request->body));
        }
        if (isset($declared['path'])) {
            $path = $request?->path;
            $lines[] = \sprintf('export type %s = %s;', $declared['path'], null !== $path ? $context->symbolForInputReference($path) : $this->renderPathParams($request->pathParams ?? []));
        }

        return implode("\n", $lines);
    }

    /**
     * The endpoint's query: its query form's type as a query string sends it, `IncludeQuery` when
     * its response has a body to shape, or both intersected.
     */
    private function queryType(CollectedEndpointContract $contract, EmitContext $context): string
    {
        $types = [];
        if (null !== $query = $contract->request?->query) {
            $types[] = 'AsQuery<' . $context->symbolForInputReference($query) . '>';
        }
        if (self::takesIncludeQuery($contract, $context->includes)) {
            $types[] = 'IncludeQuery';
        }

        return implode(' & ', $types);
    }

    /**
     * Whether a caller cannot leave the query out: its contract requires a key (`product: string`,
     * not `product?: string`, `product: ?string` nor `product: string|null`, which a query string
     * sends by leaving it out), its own or one an intersection takes from the
     * shape it extends, so a call without one is refused. The include parameters are all optional,
     * and a query whose contract cannot be read as a shape stays optional — its form's `required`
     * defaults to true for every filter and would make nearly every query mandatory.
     */
    private static function requiresQuery(CollectedEndpointContract $contract): bool
    {
        foreach ($contract->request?->query?->keys()->fields ?? [] as $key) {
            if (!$key->mayBeLeftOut(inQuery: true)) {
                return true;
            }
        }

        return false;
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

    /**
     * @param array{query?: string, body?: string, path?: string, map: string, result: string} $declared
     */
    private function renderResult(CollectedEndpointContract $contract, array $declared, EmitContext $context): string
    {
        $responses = $contract->responses;
        usort($responses, static fn(CollectedApiResponseClass $left, CollectedApiResponseClass $right): int => $left->status <=> $right->status);

        $lines = [\sprintf('export type %s = {', $declared['map'])];
        foreach ($responses as $response) {
            $lines[] = \sprintf('  %d: %s;', $response->status, $context->responseSymbolName($response));
        }
        $lines[] = '};';
        $lines[] = \sprintf('export type %s = EndpointResult<%s>;', $declared['result'], $declared['map']);

        return implode("\n", $lines);
    }

    /**
     * `export const ListProjects: Endpoint<ListProjectsEndpointMap, { query?: ListProjectsQuery }> = {…}`:
     * the method and path to call, typed with the endpoint's responses and the inputs it takes —
     * its path params and body when it has them, and its query: `query?` when a caller may leave it
     * out, `query` when its contract requires a key.
     *
     * @param array{query?: string, body?: string, path?: string, map: string, result: string} $declared
     */
    private function renderEndpoint(CollectedEndpointContract $contract, array $declared, EmitContext $context): string
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
        if (isset($declared['path'])) {
            $inputs[] = 'path: ' . $declared['path'];
        }
        if (isset($declared['query'])) {
            $inputs[] = (self::requiresQuery($contract) ? 'query: ' : 'query?: ') . $declared['query'];
        }
        if (isset($declared['body'])) {
            $inputs[] = 'body: ' . $declared['body'];
        }

        $type = $declared['map'];
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
