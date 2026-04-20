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
- PHPStan rules for:
  - `#[ApiResponses]` on routed API controller methods
  - `#[ApiRequest]` on routed mutating API controller methods
  - returned/thrown typed response classes being declared in `#[ApiResponses]`
  - `ContractFormType<TData>` syncing with `data_class`, `_self`, mapped fields, `property_path`, nested custom forms, enums, dates, collections, and common scalar leaf types
  - `_self` shape naming: no `Id` suffix on string reference fields, and no `entityType` / `entityId` pair (use a compound `entity: "{type}-{uuid}"`)

For request contracts, `query` and `body` point at Symfony form types directly. TypeBridge resolves the form's `data_class` and uses that class's `_self` definition as the generated wire contract.
Custom forms participating in request contracts must implement `PTGS\TypeBridge\Contract\ContractFormType`.

The application remains responsible for runtime HTTP emission.

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

## Testing

```bash
vendor/bin/phpunit
```
