# TypeBridge Bundle — Specification v2

A Symfony bundle for statically enforced API response contracts and generated TypeScript types.

TypeBridge keeps PHP as the source of truth, uses PHPStan to verify the serialization chain, and generates TypeScript from the verified contract model. It is a static-analysis and code-generation tool: the application remains responsible for turning typed response objects into actual HTTP responses at runtime.

## Goals

TypeBridge exists to achieve two outcomes:

1. **Self-documenting APIs that are forced to stay in sync**
2. **Reliable generated TypeScript response types**

The key design principle is that the API contract must be declared once, verified statically, and then serialized faithfully by the application runtime.

---

## The Problem

In a typical Symfony API, the actual JSON contract is spread across:

- Controller return values
- Error throwing paths
- Normalizers
- DTOs / entities / value objects
- Ad hoc runtime listeners
- Manual frontend TypeScript

That creates three kinds of drift:

1. The normalizer can diverge from the documented shape
2. The controller can return or throw outcomes not reflected in frontend types
3. The runtime serializer can emit JSON that differs from what the static model assumed

TypeBridge solves this by making the endpoint contract explicit and machine-readable, and by making the application/runtime boundary explicit about what must be serialized.

---

## High-Level Design

TypeBridge has four contract layers:

1. **Shape layer**: PHPStan type aliases define payload shapes
2. **Normalizer layer**: normalizers return those shapes and are PHPStan-checked
3. **Response layer**: response classes define top-level response bodies and carry their HTTP status via marker interfaces
4. **Endpoint layer**: a single `#[ApiResponses]` attribute declares the complete status map for a controller method

TypeBridge then:

- Verifies the chain with PHPStan rules
- Verifies controller returns and throws match the declared endpoint contract
- Generates TypeScript interfaces and endpoint result unions from the verified model
- Defines the contract the application runtime must serialize faithfully

---

## Core Principles

### 1. PHP Owns the Contract

PHP classes and PHPStan types are the canonical source of truth. TypeScript is generated from them.

### 2. Endpoint Contracts Are Explicit

Each controller method declares its full API response contract with a single attribute.

### 3. Status Is Owned by Response Classes

Response classes implement exactly one HTTP status marker interface. The endpoint attribute references classes, not raw status codes.

### 4. The Application Owns Runtime Emission

TypeBridge does not own runtime response emission. The application is responsible for converting typed response objects into HTTP responses. TypeBridge defines the static contract those runtime adapters must honor.

### 5. Static Analysis Is a Guardrail, Not a Fantasy

TypeBridge aims for strong guarantees over declared response contracts. It does not claim to model undeclared infrastructure failures or arbitrary Symfony/framework exceptions unless the application chooses to surface those through typed API response classes.

---

## Bundle-Provided Types

TypeBridge provides:

```php
namespace TypeBridge;

interface ApiResponse {}

interface ApiSuccessResponse extends ApiResponse {}

interface ApiErrorResponse extends ApiResponse, \Throwable {}

abstract class ThrowableApiResponse extends \RuntimeException implements ApiErrorResponse
{
}
```

It also provides status marker interfaces:

```php
namespace TypeBridge\Status;

interface HttpOk extends \TypeBridge\ApiSuccessResponse {}
interface HttpCreated extends \TypeBridge\ApiSuccessResponse {}
interface HttpAccepted extends \TypeBridge\ApiSuccessResponse {}
interface HttpNoContent extends \TypeBridge\ApiSuccessResponse {}

interface HttpBadRequest extends \TypeBridge\ApiErrorResponse {}
interface HttpUnauthorized extends \TypeBridge\ApiErrorResponse {}
interface HttpForbidden extends \TypeBridge\ApiErrorResponse {}
interface HttpNotFound extends \TypeBridge\ApiErrorResponse {}
interface HttpConflict extends \TypeBridge\ApiErrorResponse {}
interface HttpUnprocessableEntity extends \TypeBridge\ApiErrorResponse {}
interface HttpInternalServerError extends \TypeBridge\ApiErrorResponse {}
```

Each response class must implement exactly one status interface.

TypeBridge ships a resolver that maps those interfaces to status codes.

---

## Shape Layer

### `_self` Type Aliases

Any class may declare a payload shape with a PHPStan type alias:

```php
/**
 * @phpstan-type _self = array{
 *     id: string,
 *     name: string,
 *     status: value-of<ProjectStatus>,
 *     createdAt: string,
 * }
 */
final class ProjectView {}
```

`_self` means: “this class describes a JSON payload shape”.

It may live on:

- DTOs
- read models
- entities, if appropriate
- virtual contract-only classes

TypeBridge does **not** require entities to be the only home for API shapes. If a response needs a dedicated read model, that is preferred over overloading a domain entity with multiple incompatible API projections.

### Imports

Nested payloads are composed with `@phpstan-import-type`:

```php
/**
 * @phpstan-import-type _self from ClientView as ClientData
 * @phpstan-import-type _self from ProjectStageView as ProjectStageData
 *
 * @phpstan-type _self = array{
 *     id: string,
 *     name: string,
 *     client: ClientData,
 *     stages: list<ProjectStageData>,
 *     status: value-of<ProjectStatus>,
 *     createdAt: string,
 * }
 */
final class ProjectDetailView {}
```

### Enums

`value-of<MyStringBackedEnum>` resolves to a TypeScript string-literal union.

---

## Normalizer Layer

Normalizers return declared payload shapes:

```php
/**
 * @template TSource of object
 * @template TShapeOwner of object
 */
interface ShapeNormalizer
{
    /**
     * @param TSource $source
     * @return array
     */
    public function normalize(object $source): array;
}
```

```php
/**
 * @implements ShapeNormalizer<Project, ProjectDetailView>
 * @phpstan-import-type _self from ProjectDetailView
 */
final class ProjectDetailNormalizer implements ShapeNormalizer
{
    public function __construct(
        private readonly ClientNormalizer $clientNormalizer,
        private readonly ProjectStageNormalizer $stageNormalizer,
    ) {}

    /**
     * @return _self
     */
    public function normalize(Project $project): array
    {
        return [
            'id' => $project->getId(),
            'name' => $project->getName(),
            'client' => $this->clientNormalizer->normalize($project->getClient()),
            'stages' => array_map(
                $this->stageNormalizer->normalize(...),
                $project->getStages(),
            ),
            'status' => $project->getStatus()->value,
            'createdAt' => $project->getCreatedAt()->format('c'),
        ];
    }
}
```

PHPStan verifies the returned array matches `_self`.

If a field is added to the contract shape and not added to the normalizer, analysis fails.

The normalizer is the explicit contract boundary between:

- a runtime PHP object graph
- a statically declared array shape

TypeBridge does **not** try to infer `_self` automatically from an object by reflection. `_self` is the target contract. The normalizer is the implementation of that contract.

### Shared Base Field Serialization

Common fields such as `id`, `createdAt`, and `updatedAt` can be centralized in an abstract normalizer helper. The helper reduces duplication, but it does not change the contract model: the concrete normalizer still returns its own `_self`, and PHPStan still verifies the final merged array.

```php
interface TimestampedResource
{
    public function getId(): string;
    public function getCreatedAt(): \DateTimeInterface;
    public function getUpdatedAt(): \DateTimeInterface;
}
```

```php
/**
 * @phpstan-type _self = array{
 *     id: string,
 *     createdAt: string,
 *     updatedAt: string,
 * }
 */
final class TimestampedView {}
```

```php
/**
 * @template TSource of TimestampedResource
 * @template TShapeOwner of object
 * @implements ShapeNormalizer<TSource, TShapeOwner>
 */
abstract class AbstractTimestampedNormalizer implements ShapeNormalizer
{
    /**
     * @param TSource $source
     * @return array{id: string, createdAt: string, updatedAt: string}
     */
    final protected function serializeBase(object $source): array
    {
        assert($source instanceof TimestampedResource);

        return [
            'id' => $source->getId(),
            'createdAt' => $source->getCreatedAt()->format('c'),
            'updatedAt' => $source->getUpdatedAt()->format('c'),
        ];
    }
}
```

```php
/**
 * @phpstan-import-type _self from TimestampedView as TimestampedData
 *
 * @phpstan-type _self = TimestampedData & array{
 *     name: string,
 *     status: value-of<ProjectStatus>,
 * }
 */
final class ProjectSummaryView {}
```

```php
/**
 * @extends AbstractTimestampedNormalizer<Project, ProjectSummaryView>
 * @phpstan-import-type _self from ProjectSummaryView
 */
final class ProjectSummaryNormalizer extends AbstractTimestampedNormalizer
{
    /**
     * @return _self
     */
    public function normalize(object $source): array
    {
        assert($source instanceof Project);

        return [
            ...$this->serializeBase($source),
            'name' => $source->getName(),
            'status' => $source->getStatus()->value,
        ];
    }
}
```

This is the intended pattern:

- shared helpers may produce reusable base fragments
- shape owners may compose those fragments with imports and intersections
- concrete normalizers still assemble the final `_self`

### Embedded Serialization via Chained Normalizers

Nested API payloads are built by composing normalizers. A parent normalizer may depend on a child normalizer, and the child’s declared `_self` becomes part of the parent’s declared `_self`.

```php
/**
 * @phpstan-type _self = array{
 *     id: string,
 *     name: string,
 * }
 */
final class ClientView {}
```

```php
/**
 * @implements ShapeNormalizer<Client, ClientView>
 * @phpstan-import-type _self from ClientView
 */
final class ClientNormalizer implements ShapeNormalizer
{
    /**
     * @return _self
     */
    public function normalize(object $source): array
    {
        assert($source instanceof Client);

        return [
            'id' => $source->getId(),
            'name' => $source->getName(),
        ];
    }
}
```

```php
/**
 * @phpstan-import-type _self from ClientView as ClientData
 *
 * @phpstan-type _self = array{
 *     id: string,
 *     name: string,
 *     client: ClientData,
 *     status: value-of<ProjectStatus>,
 * }
 */
final class ProjectDetailView {}
```

```php
/**
 * @implements ShapeNormalizer<Project, ProjectDetailView>
 * @phpstan-import-type _self from ProjectDetailView
 */
final class ProjectDetailNormalizer implements ShapeNormalizer
{
    public function __construct(
        private readonly ClientNormalizer $clientNormalizer,
    ) {}

    /**
     * @return _self
     */
    public function normalize(object $source): array
    {
        assert($source instanceof Project);

        return [
            'id' => $source->getId(),
            'name' => $source->getName(),
            'client' => $this->clientNormalizer->normalize($source->getClient()),
            'status' => $source->getStatus()->value,
        ];
    }
}
```

The parent normalizer does not “serialize the child object somehow later”. It must call the child normalizer and embed the child’s already-typed array directly:

```json
{
  "id": "proj_123",
  "name": "Studio Fitout",
  "client": {
    "id": "client_456",
    "name": "Acme"
  },
  "status": "active"
}
```

That is the recommended pattern for embedded serialization:

- one shape owner per JSON fragment
- one normalizer that implements that fragment
- parent normalizers compose child normalizers explicitly

---

## Response Layer

Response classes describe the **top-level HTTP body**, not just nested data fragments.

Response classes wrap values that are already in API-contract form. In practice, that means:

- scalars
- `null`
- arrays whose types are declared in PHPDoc or native signatures
- lists / shapes composed from `_self`

Response DTOs must not contain raw entities or other domain objects that still need serialization.

### Success Responses

```php
use TypeBridge\Status\HttpOk;

/**
 * @phpstan-import-type _self from ProjectDetailView as ProjectData
 */
final class ShowProjectResponse implements HttpOk
{
    public function __construct(
        /** @var ProjectData */
        public readonly array $project,
    ) {}
}
```

```php
use TypeBridge\Status\HttpCreated;

/**
 * @phpstan-import-type _self from ProjectDetailView as ProjectData
 */
final class CreateProjectResponse implements HttpCreated
{
    public function __construct(
        /** @var ProjectData */
        public readonly array $project,
    ) {}
}
```

### No-Content Responses

```php
use TypeBridge\Status\HttpNoContent;

final class DeleteProjectResponse implements HttpNoContent
{
}
```

`204` responses must be bodyless. TypeBridge emits `null` with status `204`.

### Error Responses

```php
use TypeBridge\ThrowableApiResponse;
use TypeBridge\Status\HttpConflict;

final class ConflictResponse extends ThrowableApiResponse implements HttpConflict
{
    public function __construct(
        public readonly string $message,
        public readonly string $conflictId,
    ) {
        parent::__construct($message);
    }
}
```

```php
use TypeBridge\ThrowableApiResponse;
use TypeBridge\Status\HttpUnprocessableEntity;

final class ValidationErrorResponse extends ThrowableApiResponse implements HttpUnprocessableEntity
{
    /**
     * @param list<array{path: string, message: string}> $errors
     */
    public function __construct(
        public readonly array $errors,
    ) {
        parent::__construct('Validation failed');
    }
}
```

Error response classes are both:

- Typed API contract objects
- Throwables that may be thrown from controllers and services

---

## Endpoint Layer

Each controller method declares its complete typed response contract with one attribute:

```php
use TypeBridge\Attribute\ApiResponses;

#[ApiResponses([
    CreateProjectResponse::class,
    ExistingProjectResponse::class,
    ValidationErrorResponse::class,
    ConflictResponse::class,
])]
public function create(): CreateProjectResponse|ExistingProjectResponse
{
    // ...
}
```

This attribute is the canonical endpoint response contract.

The attribute is intentionally flat. It lists the response classes that belong to the endpoint contract, and TypeBridge infers from each class:

- whether it is a success or error response
- which HTTP status it represents
- which response body type it serializes

### Request/Input Direction

Request-side support should mirror the same explicit endpoint-contract philosophy.

Controllers should eventually declare accepted input with one method-level attribute, separate from but analogous to `#[ApiResponses]`:

```php
use PTGS\TypeBridge\Attribute\ApiRequest;

#[ApiRequest(
    query: ProjectFiltersType::class,
    body: CreateProjectRequestType::class,
    path: ProjectPathParams::class,
)]
```

That keeps the request side flat and explicit while still distinguishing the three channels TypeBridge needs for generated client types:

- `query`: query-string/filter/search/pagination input
- `body`: structured request-body input
- `path`: route parameters, declared separately because they are not a Symfony Form concern

Custom Symfony form types that participate in TypeBridge request contracts should implement a TypeBridge marker interface such as `ContractFormType<TData>`. That gives static analysis a stable hook for validating `data_class`, nested custom forms, and controller declarations.

The long-term request-side model should come from a combination of:

- the declared Symfony form class
- the typed backing data class used by that form
- the built form tree
- a limited set of validation constraints that tighten the generated TypeScript contract

In particular, request-side typing must distinguish between:

- PHP binding reality, where form data properties are often nullable during submit
- API contract reality, where `NotBlank` / `NotNull` can still make a field compulsory in generated TypeScript

That means request-side generation should not blindly mirror DTO property nullability. It should synthesize the contract from both the backing data type and the form/validation layer.

### Current Request Slice

The current implementation does not generate request TS by inferring the full form tree. Instead it uses the form only to resolve and validate the contract source:

- collect explicit endpoint request contracts
- record query/body/path separately
- resolve query/body forms through their configured `data_class`
- require request `data_class` types to declare `_self`
- generate endpoint-local TypeScript aliases such as `<Endpoint>Query`, `<Endpoint>Body`, and `<Endpoint>PathParams`
- validate analyzable contract forms with PHPStan against `data_class`, mapped fields, `property_path`, nested custom forms, enums, dates, collections, and common scalar leaf types

That keeps `_self` as the canonical wire contract while still using the built form as a sync boundary.

### Why an Attribute Instead of `@throws`

`@throws` remains useful documentation and may be cross-checked, but it is not the primary contract source because:

- Some errors may be returned instead of thrown
- `@throws` is documentation-oriented
- It is too easy for undocumented exceptions to leak in

TypeBridge may optionally verify:

- Every thrown typed `ApiErrorResponse` class is declared in `#[ApiResponses([...])]`
- Every declared typed error class is either thrown or intentionally marked as forwarded by the method

### Why Classes Instead of a Raw `code => class` Map

The endpoint attribute lists classes, not status codes, because the status belongs to the response class via its marker interface. That avoids two sources of truth.

---

## Application Runtime Contract

TypeBridge does not own runtime response emission. The application may use listeners, responders, adapters, or controller helpers however it wants.

### Runtime Contract

The full runtime contract is:

```text
object -> normalizer -> typed array (_self) -> response DTO -> application runtime -> JSON
```

Each step has one responsibility:

1. **Object**: domain entity, read model source, or service result
2. **Normalizer**: converts the object into the array shape declared by `_self`
3. **Response DTO**: defines the top-level HTTP body and status-bearing response type
4. **Application runtime**: serializes the response DTO into the actual HTTP response

This is the critical design rule:

- `_self` declares the array shape
- the normalizer implements that shape
- the response DTO wraps that shape
- the application runtime must serialize that declared response DTO faithfully

TypeBridge does **not** serialize arbitrary objects into `_self` automatically.

TypeBridge guarantees that generated TypeScript matches the declared PHP contract model. It does **not** by itself guarantee that the wire JSON matches that contract unless the application runtime honors it.

### Example: Object to Shape to JSON

```php
/**
 * @phpstan-type _self = array{
 *     id: string,
 *     name: string,
 *     status: value-of<ProjectStatus>,
 * }
 */
final class ProjectDetailView {}
```

```php
/**
 * @implements ShapeNormalizer<Project, ProjectDetailView>
 * @phpstan-import-type _self from ProjectDetailView
 */
final class ProjectDetailNormalizer implements ShapeNormalizer
{
    /**
     * @return _self
     */
    public function normalize(object $source): array
    {
        assert($source instanceof Project);

        return [
            'id' => $source->getId(),
            'name' => $source->getName(),
            'status' => $source->getStatus()->value,
        ];
    }
}
```

```php
use TypeBridge\Status\HttpOk;

/**
 * @phpstan-import-type _self from ProjectDetailView as ProjectData
 */
final class ShowProjectResponse implements HttpOk
{
    public function __construct(
        /** @var ProjectData */
        public readonly array $project,
    ) {}
}
```

```php
#[ApiResponses([ShowProjectResponse::class, NotFoundResponse::class])]
public function show(Project $project): ShowProjectResponse
{
    return new ShowProjectResponse(
        project: $this->projectDetailNormalizer->normalize($project),
    );
}
```

At runtime the application serializer should emit:

```json
{
  "project": {
    "id": "proj_123",
    "name": "Studio Fitout",
    "status": "active"
  }
}
```

Responses outside the TypeBridge contract model are out of scope. For example:

- raw `JsonResponse`
- streamed/file responses
- arbitrary framework exceptions not surfaced as typed `ApiErrorResponse` classes

---

## TypeScript Generation

TypeBridge emits:

1. shape interfaces from `_self`
2. response DTO interfaces from typed response classes
3. endpoint result unions from `#[ApiResponses]`

### Shapes

```ts
export interface ProjectDetailView {
  id: string;
  name: string;
  status: ProjectStatus;
}
```

### Response DTOs

```ts
export interface ShowProjectResponse {
  project: ProjectDetailView;
}
```

```ts
export interface ValidationErrorResponse {
  errors: {
    path: string;
    message: string;
  }[];
}
```

### Endpoint Maps and Result Unions

```ts
export type ListProjectsEndpointMap = {
  200: ListProjectsResponse;
  422: ValidationErrorResponse;
};

export type EndpointResult<M extends Record<number, unknown>> = {
  [S in keyof M & number]: {
    ok: S extends 200 | 201 | 202 | 204 ? true : false;
    status: S;
    data: M[S];
  };
}[keyof M & number];

export type ListProjectsResult = EndpointResult<ListProjectsEndpointMap>;
```

That gives the frontend:

```ts
const response = await api.projects.list();

if (response.status === 200) {
  response.data.projects;
}

if (response.status === 422) {
  response.data.errors;
}
```

That is where the flat `#[ApiResponses([...])]` model pays off: the generator has a complete endpoint contract, and can produce methods whose `response.status` narrows `response.data` automatically.

### Generated Client Direction

The intended next step is generated API methods such as:

```ts
Promise<EndpointResult<MyEndpointMap>>
```

or domain-specific wrappers:

```ts
const response = await api.projects.create(body);

if (response.status === 201) {
  response.data.project;
}
```

TypeBridge does not need to own the runtime JS client to support this. It only needs to generate a contract shape usable by a thin fetch wrapper.

---

## PHPStan Rules

The static-analysis layer is the enforcement mechanism.

### Implemented Today

- routed API controller methods must declare `#[ApiResponses([...])]`
- routed `POST`/`PUT`/`PATCH` API controller methods must declare `#[ApiRequest(...)]`
- concrete typed `ApiResponse` return types must be listed in `#[ApiResponses([...])]`
- typed `ApiErrorResponse` throws in controller bodies must be listed in `#[ApiResponses([...])]`
- `ContractFormType<TData>` must stay aligned with its configured `data_class`
- contract form `data_class` types must declare `_self`
- mapped contract form fields are checked against the backing object graph, including `property_path`
- nested custom compound forms must also participate in the TypeBridge contract subset

The remaining rules below are still part of the intended direction.

### Rule 1: `ShapeNormalizerReturnRule`

If a normalizer imports `_self` from a shape owner, its `normalize()` return type must match that `_self`.

### Rule 2: `ResponseClassStatusRule`

Every response class must implement exactly one known HTTP status interface.

### Rule 3: `ResponseClassBodyRule`

- `204` responses must be bodyless
- non-`204` responses must have typed public payload properties

### Rule 4: `ResponseClassApiKindRule`

Every response class must implement exactly one of:

- `ApiSuccessResponse`
- `ApiErrorResponse`

In practice, the status marker interfaces can extend those base interfaces so the kind is inferred.

### Rule 5: `EndpointReturnSubsetRule`

Every response class returned by a controller method must be declared in `#[ApiResponses([...])]`.

### Rule 6: `EndpointThrowsSubsetRule`

Every typed API error response thrown from a controller method must be declared in `#[ApiResponses([...])]`.

### Rule 7: `ApiResponsesClassKindRule`

Every class listed in `#[ApiResponses([...])]` must implement exactly one of:

- `ApiSuccessResponse`
- `ApiErrorResponse`

and exactly one known HTTP status marker interface.

### Rule 8: `DuplicateStatusPerEndpointRule`

Fail if an endpoint declares two different response classes with the same HTTP status unless explicitly allowed in a future extension.

### Rule 9: `NoRawObjectsInResponseDtoRule`

Response DTO properties may not contain raw entities/domain objects. They must contain already-serialized contract values.

### Rule 10: `UnusedResponseClassRule` (optional)

Warn when a response class is never referenced by any `#[ApiResponses]` attribute.

---

## Examples

### Simple Success Response

```php
final class ShowProjectResponse implements HttpOk
{
    public function __construct(
        /** @var ProjectData */
        public readonly array $project,
    ) {}
}
```

### List Response

```php
final class ListProjectsResponse implements HttpOk
{
    /**
     * @param list<ProjectData> $projects
     */
    public function __construct(
        public readonly array $projects,
    ) {}
}
```

### Paginated Response

```php
final class ListProjectsPaginatedResponse implements HttpOk
{
    /**
     * @param list<ProjectData> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {}
}
```

### Composite Dashboard Response

```php
final class DashboardResponse implements HttpOk
{
    /**
     * @param list<ProjectSummaryData> $recentProjects
     * @param list<TaskSummaryData> $myTasks
     */
    public function __construct(
        public readonly array $recentProjects,
        public readonly array $myTasks,
        public readonly array $stats,
    ) {}
}
```

### Enum That Serializes as an Object

If an enum is a full serialized contract object, it should own its own `_self` shape.

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

A consumer can then import the enum-owned object projection:

```php
/**
 * @phpstan-import-type _self from ProjectStatus as ProjectStatusData
 *
 * @phpstan-type _self = array{
 *     statusDetail: ProjectStatusData,
 * }
 */
final class ProjectAdminView {}
```

Generated TypeScript should keep both:

```ts
export type ProjectStatus = 'draft' | 'active';

export interface ProjectStatusData {
  value: ProjectStatus;
  label: string;
  color: string;
}
```

### Projection Extension

A base projection plus two specialized projections should be expressed through shape intersections, not PHP inheritance:

```php
/**
 * @phpstan-type _self = array{
 *     id: string,
 *     name: string,
 *     status: value-of<ProjectStatus>,
 * }
 */
final class ProjectBaseView {}
```

```php
/**
 * @phpstan-import-type _self from ProjectBaseView as ProjectBaseData
 *
 * @phpstan-type _self = ProjectBaseData & array{
 *     canEdit: bool,
 *     ownerNotes: string|null,
 * }
 */
final class ProjectOwnerView {}
```

```php
/**
 * @phpstan-import-type _self from ProjectBaseView as ProjectBaseData
 *
 * @phpstan-type _self = ProjectBaseData & array{
 *     internalNotes: string|null,
 *     auditTrail: list<string>,
 * }
 */
final class ProjectAdminView {}
```

Generated TypeScript:

```ts
export interface ProjectOwnerView extends ProjectBaseView {
  canEdit: boolean;
  ownerNotes: string | null;
}

export interface ProjectAdminView extends ProjectBaseView {
  internalNotes: string | null;
  auditTrail: string[];
}
```

---

## Implementation Sketch

### Collector Pipeline

1. Scan PHP classes for `@phpstan-type` definitions
2. Parse `_self` and supporting aliases into a PHPStan-shape AST
3. Resolve `@phpstan-import-type`
4. Scan response classes implementing `ApiResponse`
5. Resolve response status from marker interfaces
6. Scan controller methods for `#[ApiResponses]`
7. Build per-domain collected contract models
8. Emit TypeScript

### Emitter Responsibilities

The emitter should:

- group output by domain
- import cross-domain shapes
- emit enums as string unions
- emit `_self` shapes as interfaces or type aliases
- emit response DTOs
- emit endpoint maps and `EndpointResult`

### Runtime Independence

The implementation should not depend on any specific serializer. The application runtime remains free to use:

- Symfony listeners
- controller helpers
- custom responders
- framework adapters

TypeBridge only defines the static contract the runtime must honor.

---

## Non-Goals for v1

- Full request body/query/header contract generation
- Runtime response emission
- Modeling every possible framework/infrastructure exception
- Replacing OpenAPI in ecosystems that already want OpenAPI as the primary source of truth

Request-side support can be added incrementally without changing that position. The first useful request-side milestone is explicit query/body/path contract collection and TypeScript generation, not full Symfony form analysis on day one.

---

## v1 Success Criteria

TypeBridge v1 is successful if it can:

1. collect `_self` shapes and imports reliably
2. collect response classes and endpoint contracts reliably
3. generate TS shape/response/result types reliably
4. enforce the declared response contract with PHPStan rules
5. make response-side API contracts meaningfully harder to let drift

---

## Final Position

The project should stay true to its original intent:

- PHP is the source of truth
- static analysis is the enforcement mechanism
- TypeScript is generated from verified PHP contracts
- runtime emission remains app-owned

That makes TypeBridge a response-contract and codegen tool, not an OpenAPI replacement framework and not a serializer framework.
