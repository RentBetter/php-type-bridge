# PHP Type Bridge

`PTGS\TypeBridge` is a static contract and TypeScript generation package for PHP APIs.

It provides:

- `#[ApiResponses([...])]` to declare flat endpoint response contracts
- `#[ApiRequest(...)]` to declare flat endpoint request contracts for query/body/path inputs
- semantic status marker interfaces such as `HttpOk` and `HttpCreated`
- collectors for `_self` PHPStan shapes, response DTOs, endpoint contracts, and Symfony form-backed request inputs
- a PHPStan extension for endpoint, contract-form, and shape-naming enforcement
- a TypeScript emitter that produces shape types, response types, request aliases, and endpoint result unions
- configurable TypeScript naming for interfaces, enum value aliases, enum `_self` objects, and endpoint alias suffixes

## Docs

- [Specification v2](docs/spec-v2.md)

## Example: serializable enum as object data

If an enum serializes itself as an object, keep the enum as the source of truth and declare `_self` on the enum.

```php
/**
 * @phpstan-type _self = array{
 *     value: value-of<ProjectStatus>,
 *     label: string,
 *     color: string,
 * }
 */
enum ProjectStatus: string implements \JsonSerializable
{
    case Draft = 'draft';
    case Active = 'active';

    /**
     * @return _self
     */
    public function jsonSerialize(): array
    {
        return [
            'value' => $this->value,
            'label' => match ($this) {
                self::Draft => 'Draft',
                self::Active => 'Active',
            },
            'color' => match ($this) {
                self::Draft => 'slate',
                self::Active => 'green',
            },
        ];
    }
}
```

Import that `_self` where needed:

```php
/**
 * @phpstan-import-type _self from ProjectStatus as ProjectStatusData
 *
 * @phpstan-type _self = array{
 *     statusDetail: ProjectStatusData,
 * }
 */
final class ProjectAdminView
{
}
```

The generated TypeScript keeps both forms:

```ts
export type ProjectStatus = 'draft' | 'active';

export interface ProjectStatusData {
  value: ProjectStatus;
  label: string;
  color: string;
}
```

Enum-owned `_self` shapes emit with a suffix (default `Data`) to avoid colliding with the enum value union. Override via `enumShapeSuffix` (see [TypeScript naming](#typescript-naming)).

When the enum's name belongs to the object it serialises to, name its value union instead with `#[ValueOfName]`. The `_self` then takes the enum's own name, as any class's `_self` does:

```php
/**
 * @phpstan-type _self = array{code: value-of<Currency>}
 */
#[ValueOfName('CurrencyCode')]
enum Currency: string implements JsonSerializable { … }
```

```ts
export type CurrencyCode = 'AUD' | 'NZD';

export interface Currency {
  code: CurrencyCode;
}
```

A shape that names the class — `currency: Currency` — is that interface, and `value-of<Currency>` is `CurrencyCode`, wherever they are written.

## TypeScript naming

If you need project-specific naming, pass a config file to the generator:

```bash
php bin/console typebridge:generate src assets/types --config=type-bridge.php
```

`type-bridge.php` should return an array:

```php
<?php

return [
    'typescript' => [
        'interfacePrefix' => 'I',
        'enumValueSuffix' => 'Id',
        'enumShapeSuffix' => '',
        'queryAliasSuffix' => 'QueryParams',
        'bodyAliasSuffix' => 'Payload',
        'pathAliasSuffix' => 'RouteParams',
        'endpointMapSuffix' => 'Responses',
        'endpointResultSuffix' => 'Outcome',
    ],
    'preserveNull' => [
        'IProject.archivedAt',
        'IProjectStage.parentId',
    ],
];
```

Defaults when unset:

| Key                    | Default       |
| ---------------------- | ------------- |
| `interfacePrefix`      | `''`          |
| `enumValueSuffix`      | `''`          |
| `enumShapeSuffix`      | `'Data'`      |
| `queryAliasSuffix`     | `'Query'`     |
| `bodyAliasSuffix`      | `'Body'`      |
| `pathAliasSuffix`      | `'PathParams'`|
| `endpointMapSuffix`    | `'EndpointMap'`|
| `endpointResultSuffix` | `'Result'`    |

The knobs are intentionally narrow:

- `interfacePrefix` applies only to declarations emitted as `interface`
- `enumValueSuffix` renames the backed-value union from `value-of<MyEnum>`
- `enumShapeSuffix` renames enum-owned `_self` types before any interface prefix is applied
- request and endpoint alias suffixes rename the flat transport helpers without changing the underlying source-of-truth PHP names

For example, the config above emits:

```ts
export type ProjectStatusId = 'draft' | 'active';
export interface IProjectStatus {
  value: ProjectStatusId;
  label: string;
  color: string;
}

export type ProjectCreatePayload = ICreateProjectRequestData;
```

TypeBridge fails fast if a custom naming scheme would emit colliding symbols in the same domain.

## Current scope

This first cut is focused on contract collection and code generation:

- `_self` shape collection with `@phpstan-import-type` support
- method-level request contract collection via `#[ApiRequest(...)]`
- Symfony form-backed request metadata collection, including configured `data_class` and built field trees
- `ContractFormType<TData>` enforcement for top-level and nested custom contract forms
- generated TypeScript request aliases for query/body/path inputs
- response DTO status resolution
- endpoint contract collection
- generated TypeScript endpoint maps and `EndpointResult` unions
- a generated `Endpoint` constant per endpoint: its method and path, typed with its responses and inputs (see [Calling an endpoint](#calling-an-endpoint))
- PHPStan rules for:
  - `#[ApiResponses]` on routed API controller methods
  - `#[ApiRequest]` on routed mutating API controller methods
  - returned/thrown typed response classes being declared in `#[ApiResponses]`
  - `ContractFormType<TData>` syncing with `data_class`, `_self`, mapped fields, `property_path`, nested custom forms, enums, dates, collections, and common scalar leaf types
  - `_self` shape naming: no `Id` suffix on string reference fields, and no `entityType` / `entityId` pair (use a compound `entity: "{type}-{uuid}"`)

For request contracts, `query` and `body` point at Symfony form types directly. TypeBridge resolves the form's `data_class` and uses that class's `_self` definition as the generated wire contract.
Custom forms participating in request contracts must implement `PTGS\TypeBridge\Contract\ContractFormType`.

A mutating route that takes no input — a `POST` that only triggers work — still carries `#[ApiRequest]`, bare. The collector records no request contract for it (the generated input schema is an empty object) and `MutatingApiRequestRequiredRule` reads the bare attribute as the explicit declaration it asks for, so the absence of input is stated rather than flagged.

The application remains responsible for runtime HTTP emission.

### Calling an endpoint

Each endpoint also emits a constant saying how to call it. It is plain data, `method` and `path`, typed with the endpoint's response map and the inputs it takes, so a client's own helpers can call any endpoint without restating its URL or its types:

```ts
export const UpdateProject: Endpoint<UpdateProjectEndpointMap, { path: UpdateProjectPathParams; body: UpdateProjectBody }> = {
  method: 'PUT',
  path: '/api/projects/{id}',
};
```

The inputs are `path` when the route has placeholders, `body` when the request has one, and `query`, which a caller may leave out. `types` is never set; it is there for a helper to read the endpoint's types off it: `E extends Endpoint<infer M, infer I>`. The path is the one Symfony serves when the `routing` config is given, its prefixes and any class-level `#[Route]` included. Without it, the path is the method attribute's own. TypeBridge sends nothing itself: how a request is made, and what an error status does, is the client's to decide.

### PHPStan types in shapes

A shape is written for PHPStan first, so TypeBridge reads the PHPStan types a codebase actually uses and emits the TypeScript that describes the same JSON:

| PHPStan | TypeScript |
|---------|------------|
| `positive-int`, `non-negative-int`, `int<0, max>` … | `number` |
| `non-empty-string`, `numeric-string`, `class-string<T>` … | `string` |
| `scalar` | `string \| number \| boolean` |
| `array-key` | `string \| number` |
| `array<array-key, V>` | `Record<string, V> \| V[]` (json_encode writes whichever the keys make it) |
| `non-empty-list<T>`, `non-empty-array<K, V>` | `T[]`, `Record<K, V>` |
| `array{id: string, ...}` (unsealed) | `{ id: string; [key: string]: unknown }` |
| `array{int, string, ...}` (unsealed tuple) | `[number, string, ...unknown[]]` |
| `self::STATUS_*`, `Foo::BAR`, `value-of<self::MODE_*>` | the constants' values: `'draft' \| 'live'` |
| `(A \| B)`, `Base & array{...}` | `A \| B`, `interface … extends Base` |
| `MoneyInterface`, `\Acme\Money` (a class) | the JSON it serialises to — see below |
| `included<T>` — a generic listed in `includes.types` | `T`, on a key absent unless asked for — see below |
| `ref<T>` — a generic listed in `includes.refTypes` | `Ref<T>`: an id a request can expand to T — see below |
| `enum<T>` — a generic listed in `includes.enumTypes` | `T`'s own type: an enum the normaliser hands over as a marker |

A refinement keeps its spelling in the parsed tree, so a shape rendered back to PHPDoc reads as it was written.

### Keys sent only when asked for

An API can leave parts of a response out unless the request asks for them (`?include=checks.debug`), and mark those keys in its shapes with a generic of its own — `included<T>`, which its PHPStan extension reads as `T|Optional<T>`. Whether such a key is there depends on two things, and the shape says which:

| Shape key | Present on the wire when… | Depends on |
|---|---|---|
| `debug: included<mixed>` | exactly when the request includes it | the request only |
| `documentation?: included<string>` | the request includes it **and** there is a value | the request **and** the data |
| `notifiedAt?: DateTimeData` | there is a value | the data only |
| `name: string` | always | nothing |

The key's `?` carries the difference, and PHPStan keeps it honest: a required key whose value could be null fails analysis, since the null would be dropped before the response is sent. A side-loaded collection is in the first row by construction — asked for, it is always there, as an empty list when nothing is referenced.

Tell TypeBridge how the project marks them:

```php
'includes' => [
    'types' => ['included'],                    // the include generics
    'sideLoadAttribute' => SideLoad::class,     // the attribute on a side-loaded response property
],
```

An included key always emits as optional, since it is absent unless asked for. The keys in the first row — required `included<>` keys, and side-loads — are listed in a `…Included` union beside the shape or response. `WithIncludes<T, P>` makes the keys a request's include paths name present, at any depth. It is declared in the shared root module when the output has one (`output.rootModule`), and in each module that needs it otherwise:

```ts
export interface SystemCheckData { name: string; debug?: unknown; documentation?: string; }
export type SystemCheckDataIncluded = 'debug';
export interface ListSystemChecksResponse { checks: SystemCheckData[]; definitions?: SystemCheckDefinitionData[]; }

// One list drives both the request and the type, so they cannot drift apart.
const CHECKS_INCLUDE = ['checks.debug', 'definitions'] as const;
type Checks = WithIncludes<ListSystemChecksResponse, typeof CHECKS_INCLUDE>;

const response = await api.get<Checks>('/checks', { params: { include: CHECKS_INCLUDE.join(',') } });
response.data.checks[0].debug;        // there: the path reaches through the list
response.data.definitions.length;      // there: a side-load asked for
```

P is a dotted path, a union of them, or a list. Each is checked against `IncludePath<T>`, which covers every key path in T to five levels, so a misspelt path (`'checks.debgu'`) does not compile. That check is against the type, not the API's include vocabulary: a path to a key that is always sent still compiles, and it changes nothing.

### References a request can expand

A related record can go out as its id and, when the request expands its path (`?expand=checks.definition`), as the record itself — the server swapping one for the other. A shape marks such a key with a generic of its own, naming the shape the record is sent as: `definition?: ref<DefinitionData>`.

```php
'includes' => [
    'types' => ['included'],
    'refTypes' => ['ref'],                      // the reference generics
],
```

It emits as `Ref<DefinitionData>`: the record's id, typed as the record types its own `id`, and flavoured with the record under a `~ref` key that is never sent. A plain id is still assignable to it and it reads as one, so a ref works as an id anywhere an id is wanted. `WithExpands<T, P>` swaps it for the record at the paths a request expanded — through lists, and on into the record, so `checks.definition.latestResult` expands a ref inside an expanded one:

```ts
export interface CheckData { name: string; definition?: Ref<DefinitionData>; }
export interface DefinitionData { id: string; name: string; latestResult?: Ref<ResultData>; }

type Checks = WithExpands<ListChecksResponse, ['checks.definition']>;
declare const checks: Checks;
checks.checks[0].definition?.name;          // the record
checks.checks[0].definition?.latestResult;  // still an id: not expanded
```

P is checked against `ExpandPath<T>` — the refs in T, and the refs inside what they expand to, five levels deep — so a path that names anything but a reference does not compile. `Ref`, `ExpandPath` and `WithExpands` are declared beside `WithIncludes`, in the root module or in each module that has a ref. The two compose: `WithIncludes<WithExpands<T, X>, P>` includes keys inside expanded records.

A normaliser that hands an enum over as a marker of its own, for its serialiser to write out, says so with a generic listed in `includes.enumTypes`: `status: enum<Status>` emits as `status: Status`, what the marker is sent as.

Any other generic TypeBridge does not know is an error that names `includes.types`, `includes.refTypes` and `includes.enumTypes`.

The query parameters a request asks with are `includes.query`, each named with its description:

```php
'includes' => [
    // …
    'query' => [
        'include' => 'Opt-in parts of the response, comma-separated dotted paths.',
        'expand' => 'References to send as their records, comma-separated dotted paths.',
    ],
],
```

Every endpoint whose success response has a body takes them, so no action declares them on its query form: its query type is `IncludeQuery` (declared once, beside `WithIncludes`), intersected with its form's type when it has one, and its MCP tool lists them among its arguments, sent in the query string. An endpoint that answers 204 has nothing to shape and takes none.

The runtime that writes these keys out — optionals, refs and enum markers — is opt-in: see [Includes and expands at runtime](#includes-and-expands-at-runtime).

### Shared declarations

The config `typeAliases` (`UuidStr`, …) and the `WithIncludes` and `WithExpands` helpers are the same everywhere. With a shared root module (`output.rootModule`) they are declared there once and each module imports what it uses; without one, each module declares its own copy. A module imports only the types it references — a class's `@phpstan-import-type` that none of its emitted shapes uses is left out.

### Modules

Each domain is one module: the directories a class sits in under the source root, `output.domainDepth` levels deep (default `1`). At 1, everything under `src/Admin/` is one `admin` module. At 2, `src/Admin/SystemChecks/` is a module of its own, `admin/systemChecks`, which keeps modules small in a codebase organised by subdomain. Modules import each other by relative path (`../../entity/genTypes`). A module that both the full pass and a discovered emitter write is written once, with the declarations of both.

Types every domain shares — money, dates, an entity reference — belong beside the config aliases rather than in a module of their own. `output.rootSources` lists the paths under the source root whose types are declared in the root module: a directory covers everything beneath it, a file only itself. It needs `output.rootModule`.

```php
'output' => [
    'rootModule' => 'genTypes.ts',
    'rootSources' => ['_', 'Entity', 'Event/EntityRef.php'],
],
```

Moving a class into or out of a root source moves its type on the next generate; the imports that name it follow.

### Classes in shapes

PHPStan-typed code often holds objects in an array that json_encode then serialises — `array{total: MoneyInterface}` — so a shape may name a class, and it means the JSON that class serialises to:

- its `_self` shape, or the nearest parent's or interface's when it declares none (`Money implements MoneyInterface` takes `MoneyInterface`'s), imported from that class's module;
- or the type it imports as its `_self`, when what it serialises to is declared elsewhere — `@phpstan-import-type MoneyData from AbstractV2Normalizer as _self`. The shape is then `MoneyData`, wherever it is written, and the class emits nothing under its own name, so a client has the one type to use;
- otherwise, the type the emitter that claims the class publishes for it — an emitter implementing `TypeSymbolEmitter`, as a serialisable enum's does;
- otherwise it is an error that says so.

The name resolves as PHP resolves it: through the file's `use` statements, in its namespace, or fully qualified. An alias of the same name always wins, so nothing that resolved before changes.

Shapes that type PHP arrays which are never serialised — working data holding `DateTimeImmutable`s or entities — have no JSON to describe. Mark the class that declares them `#[PhpStanOnly]` and TypeBridge emits none of its aliases — or `#[PhpStanOnly(['Draft'])]` for only those named, when the class also declares shapes that are sent. One-argument `array<V>` is still refused: PHPStan reads it as `array<array-key, V>`, so write that — or `list<V>` / `array<string, V>` when that is what it is.

## Includes and expands at runtime

The sections above type a body the app writes. TypeBridge can also write it: an opt-in runtime turns the markers a normaliser returns into the body the request asked for — `?include=`, `?expand=` with field lists, and enums whole or as bare ids. Controllers return their response DTO as before and never see any of it.

### Turning it on

```yaml
# config/packages/type_bridge.yaml
type_bridge:
    includes: true
    # or, with its options:
    # includes:
    #     enum_format_header: X-Enum-Format                   # the header whose value `id` asks for bare ids
    #     json_encoding_options: !php/const JSON_UNESCAPED_UNICODE   # json_encode() flags; JsonResponse's defaults otherwise
```

Off, the bundle behaves exactly as before. On, `IncludeResponseSubscriber` writes every `ApiSuccessResponse` that has a body, on `kernel.view` at priority 0, ahead of `TypeBridgeResponseSubscriber` (which still answers 204s). It reads `include` and `expand` from the query string whatever the method, so name those two in `includes.query` and TypeBridge publishes them on every such endpoint (see [References a request can expand](#references-a-request-can-expand)).

### Marking a shape in the normaliser

A normaliser uses the `IncludeMarkers` trait — a trait, so it sits beside whatever base class the normaliser already extends:

```php
use PTGS\TypeBridge\Normalizer\IncludeMarkers;

/**
 * @phpstan-type _self = array{
 *     id: string,
 *     name: string,
 *     status: enum<CheckStatus>,
 *     definition?: ref<DefinitionData>,
 *     debug: included<array<string, mixed>>,
 * }
 *
 * @implements ShapeNormalizer<Check, self>
 */
final class CheckNormalizer extends AbstractShapeNormalizer implements ShapeNormalizer
{
    use IncludeMarkers;

    public function normalize(object $check): array
    {
        return $this->filterNulls([
            'id' => $check->getId(),
            'name' => $check->getName(),
            'status' => $this->enum($check->getStatus()),
            'definition' => $this->ref($check->getDefinition()),
            'debug' => $this->optional(fn () => $check->getDebug()),
        ]);
    }
}
```

| Helper | Shape key | Sent as |
|---|---|---|
| `optional(fn () => …)` | `included<T>` | absent unless `?include=` names its path; the closure runs only then |
| `ref($entity)`, `refs($entities)` | `ref<RecordData>`, `list<ref<RecordData>>` | the id, or the record where `?expand=` names its path |
| `enum($case)` | `enum<Status>` | an `ApiEnum`'s object, or its id on request; any other backed enum's value |

`ref()`, `refs()` and `enum()` give null for null. `ref()` names the entity's class and its `getId()` (a string, an int or a `Stringable`, sent as a string); override `refClass()` or `refId()` for entities that carry their id another way, or write `new Ref(Definition::class, $id)` for a relation held only as an id. Keep an `optional()` closure to data in hand: it runs once per row, so one that queries is an N+1 — a related entity is a `ref()`.

### Expanding a reference

A normaliser that can send the record a ref points at implements `RefNormalizer`:

```php
final class DefinitionNormalizer implements ShapeNormalizer, RefNormalizer
{
    // normalize() as for any ShapeNormalizer, and:

    public static function entityClass(): string
    {
        return Definition::class;
    }

    public function resolveMany(array $ids): array
    {
        return array_map($this->normalize(...), $this->definitions->findBy(['id' => $ids]));
    }
}
```

The bundle tags every autoconfigured implementation, and `RefRegistry` matches a ref's class with `is_a()`, so a Doctrine proxy finds its entity's normaliser. Each record carries its `id`. Every ref a request reaches at one level is fetched with one `resolveMany()` per entity, each id once, and the records are walked in turn: a path goes on into them (`checks.definition.latestResult`), `include` paths reach inside them, and a reference cycle is followed only as far as a path spells it out. A ref that no `RefNormalizer` claims, or a record it does not return, is a server bug (`LogicException`), not a 422.

### Sending an enum whole

An enum that goes out as an object implements `PTGS\TypeBridge\Enum\ApiEnum`, which asks for `id()` and `jsonSerialize(): array`:

```php
enum CheckStatus: string implements ApiEnum
{
    case Ok = 'ok';
    case Warning = 'warning';

    public function id(): string
    {
        return strtoupper($this->name);
    }

    public function jsonSerialize(): array
    {
        return ['id' => $this->id(), 'name' => ucfirst($this->value)];
    }
}
```

`status: enum<CheckStatus>` goes out as `{"id": "WARNING", "name": "Warning"}`, or as `"WARNING"` to a request sending `X-Enum-Format: id` — what an MCP client wants, since a model reads an id as well as a label and its tools take ids back. An expand naming the enum's path sends it whole even then, or cut to a field list: `checks(name,status(name))`. A backed enum that is not an `ApiEnum` goes out as its value either way.

### Paths

- Comma-separated dotted paths from the response's root. A path implies its prefixes, and a list's items sit at the list's own path.
- `include`: `x.*` opens every optional key one level under x — never at the root, never mid-path.
- `expand`: no `*`, since each expansion is a query. A field list narrows what is at a path to those keys and its `id`, keeping any key a deeper path goes on into: a record (`checks.definition(name)`), the rows themselves (`checks(name,status)`), or nested (`checks(name,status(name))`). The same path given twice merges its lists, a path without a list asks for the whole thing, and an unknown field is skipped.
- A 422 for a root that is not a field of the response, a malformed path, a field list on `include`, or an expand that ends at something that is neither a ref, an `ApiEnum` nor narrowed by a list. It is the app's own 422: the subscriber builds it with the bound `ValidationErrorResponseFactory`, its path `include` or `expand`.

Markers are found in the response's public properties and the arrays beneath them; any other object is left for `json_encode()`, and not looked into.

### PHPStan

```neon
includes:
    - vendor/ptgs/php-type-bridge/includes.neon
```

It reads `included<T>` as `T|Optional<T>`, `ref<T>` as a `Ref` and `enum<E>` as an `EnumCase<E>`, so PHPStan holds each normaliser to the shape it says it returns. It also registers `ShapeValuesRule` (`typeBridge.shapeValue`): a shape written by a normaliser that uses `IncludeMarkers` — one it declares, or its `ShapeNormalizer` owner's — holds scalars, lists, shapes and the markers, never an object, so every decision about what goes out is made in the normaliser. The generic names default to `included`, `ref` and `enum`; an app that names them otherwise mirrors its TypeBridge config, replacing the list:

```neon
parameters:
    typeBridgeIncludes:
        types!: [opt]
```

## PHPStan

Include the packaged rules in your project config:

```neon
includes:
    - vendor/ptgs/php-type-bridge/extension.neon
```

The rules intentionally target the analyzable subset. If a contract form becomes too dynamic, TypeBridge should fail instead of guessing.

### Shape naming allowlist

The `_self` shape-naming rules flag any string field ending in `Id` as a reference that should be named after the entity (e.g. `project` rather than `projectId`). External-system identifiers can be allow-listed:

```neon
parameters:
    typeBridge:
        shapeNaming:
            allowIdSuffix:
                - stripeCustomerId
                - xeroInvoiceId
```

### Preserve-null consistency

Nullable fields in `@phpstan-type` shapes carry two distinct meanings:

- `?T` — the value is optional. Wire payload omits the field when null; TypeScript emits `field?: T`.
- `T|null` — null is semantically meaningful (e.g. `archivedAt: null` for "never archived"). Wire payload always sends the key; TypeScript emits `field: T | null`.

To keep the two consistent across a codebase, list every field whose null is meaningful in `preserveNull`. The PHPStan rule enforces that listed fields use `T|null` and unlisted fields use `?T`:

```neon
parameters:
    typeBridge:
        preserveNull:
            - IProject.archivedAt
            - IProjectStage.parentId
```

Mirror the same list in `type-bridge.php` so the emitter validates at codegen time too (the emitter throws on the same mismatch).

## Testing

```bash
vendor/bin/phpunit
```
