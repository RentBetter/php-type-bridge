<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

use PTGS\TypeBridge\Support\TypeScriptTypes;

/**
 * A single route-derived path parameter: the placeholder name and its resolved TS type.
 * The route requirement is retained as the router holds it — Symfony's Route takes a leading `^`
 * or `\A` and a trailing `$` or `\z` off — so later versions can attach a Zod schema or a
 * value-resolver target keyed off the same {@see \PTGS\TypeBridge\Routing\PathParam} pattern,
 * without changing this object's consumers.
 *
 * `utf8` is the route's own `utf8` option when it states one — whether the router matches the
 * requirement by character (PCRE `u`) or by byte — and null when it leaves it to the router's default.
 *
 * `standsFor` is the TypeScript a project-wide alias `tsType` names (`UuidStr` => `string`), read
 * through any alias it names in turn; null where `tsType` is no such alias.
 */
final readonly class CollectedPathParam
{
    public function __construct(
        public string $name,
        public string $tsType,
        public ?string $requirement = null,
        public ?bool $utf8 = null,
        public ?string $standsFor = null,
    ) {}

    /**
     * The JSON Schema type a tool takes it as: a number or a boolean where its type is one, and a
     * string otherwise — what a path segment is, whatever narrower type it is typed with.
     */
    public function schemaType(): string
    {
        return TypeScriptTypes::jsonType($this->standsFor ?? $this->tsType) ?? 'string';
    }
}
