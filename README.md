# PHP Type Bridge

`PTGS\TypeBridge` is a static contract and TypeScript generation package for PHP APIs.

It provides:

- `#[ApiResponses([...])]` to declare flat endpoint response contracts
- `#[ApiRequest(...)]` to declare flat endpoint request contracts for query/body/path inputs
- semantic status marker interfaces such as `HttpOk` and `HttpCreated`
- collectors for `_self` PHPStan shapes, response DTOs, endpoint contracts, and Symfony form-backed request inputs
- a PHPStan extension for endpoint and contract-form enforcement
- a TypeScript emitter that produces shape types, response types, request aliases, and endpoint result unions

## Docs

- [Specification v2](docs/spec-v2.md)
- [Session notes](docs/session-notes.md)

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

Enum-owned `_self` shapes emit as `<EnumName>Data` to avoid colliding with the enum value union.

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

For request contracts, `query` and `body` point at Symfony form types directly. TypeBridge resolves the form's `data_class` and uses that class's `_self` definition as the generated wire contract.
Custom forms participating in request contracts must implement `PTGS\TypeBridge\Contract\ContractFormType`.

The application remains responsible for runtime HTTP emission.

## PHPStan

Include the packaged rules in your project config:

```neon
includes:
    - vendor/rentbetter/php-type-bridge/extension.neon
```

The rules intentionally target the analyzable subset. If a contract form becomes too dynamic, TypeBridge should fail instead of guessing.

## Testing

```bash
vendor/bin/phpunit
```
