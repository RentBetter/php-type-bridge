# Session Notes

`/Users/paul/rb/type-bridge` is the only source of truth going forward.

## Current State

- Standalone local repo initialized at `~/rb/type-bridge`
- Package namespace is `PTGS\TypeBridge`
- Flat endpoint contract attribute is `#[ApiResponses([...])]`
- Status is inferred from semantic interfaces like `HttpOk`, `HttpCreated`, `HttpConflict`
- Runtime emission is explicitly app-owned
- Generated TS includes endpoint maps and `EndpointResult` unions

## Implemented And Tested

- `_self` collection from PHPDoc
- `@phpstan-import-type` resolution
- string-backed enum value unions via `value-of<Enum>`
- response DTO collection and status resolution
- endpoint contract collection
- TypeScript emission for shapes, responses, endpoint maps, and result unions
- projection extension via `BaseData & array{...}`
- enum-owned serialized object shapes via `<EnumName>Data`
- request contract collection via `#[ApiRequest(...)]`
- real Symfony Form integration for request contracts
- request alias generation for query/body/path endpoint inputs
- `ContractFormType<TData>` enforcement for top-level and nested custom contract forms
- PHPStan extension rules for:
  - routed API methods requiring `#[ApiResponses]`
  - routed mutating API methods requiring `#[ApiRequest]`
  - returned/thrown typed responses matching `#[ApiResponses]`
  - contract forms syncing with `data_class`, `_self`, mapped fields, `property_path`, nested custom forms, and common analyzable field types

## Current Test Status

- PHPUnit: `29 tests, 203 assertions`

## Known Gaps

- Header contract generation is not implemented yet
- Parser currently supports one top-level base intersection like `BaseData & array{...}`
- No conflict detection yet for duplicate fields between base and child projections
- No duplicate-status validation yet at endpoint collection time
- PHPStan response-side coverage is intentionally a subset:
  - it checks concrete return types and direct typed throws
  - it does not yet prove exhaustiveness of all forwarded framework/runtime error paths
- Form-side static validation is intentionally limited to the analyzable subset and should fail closed for forms that become too dynamic

## Request-Side Direction

- Request-side contracts should mirror the response-side endpoint model: explicit controller-level declaration, collected model objects, then TS emission
- The likely method-level attribute is `#[ApiRequest(...)]` with distinct `query`, `body`, and `path` slots
- Query/body slots should point directly at Symfony form classes
- Path params should be declared separately from forms
- Long-term request-side typing should come from the combination of:
  - typed backing data classes
  - built Symfony form trees
  - limited validation constraints such as `NotBlank` / `NotNull` for TS requiredness
- DTO/property nullability alone is not sufficient for request-side TS because Symfony form binding may temporarily assign `null` even when validation later requires the field
- The first practical slice is smaller:
  - explicit request contract collection
  - generated TS aliases for query/body/path per endpoint
  - `_self` remains the request-side wire contract source
  - built forms are now used for sync checks, not as the canonical TS source

## Naming Direction

- Keep endpoint transport aliases generated and endpoint-local:
  - `<Endpoint>Query`
  - `<Endpoint>Body`
  - `<Endpoint>PathParams`
- `query` and `body` should be declared as form classes; TypeBridge resolves `data_class` from the form and `_self` from that class
- custom contract forms should implement `ContractFormType<TData>`
- Distinguish request envelopes from nested payloads:
  - `CreateProjectRequestData` for `{ project: ... }`
  - `CreateProjectInputData` for the nested mutation payload
- Reserve shared create/update payload bases for reusable mutation shapes such as `ProjectMutationData`
- Keep response-side read models distinct with names like `ProjectView`
- The fixture set now includes both create and update request envelopes so naming can be judged against both operations

## Practical Notes

- Enum-owned `_self` shapes default to `<EnumName>Data`, but TS naming is now configurable
- Custom TS naming fails fast if it would emit colliding symbols in the same domain
- Projection inheritance is shape-based, not PHP-class-inheritance-based
- Avoid staging work in other repos; edit this repo directly
