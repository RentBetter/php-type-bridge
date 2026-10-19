<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Model;

/**
 * A single route-derived path parameter: the placeholder name and its resolved TS type.
 * The route requirement is retained as the router holds it — Symfony's Route takes a leading `^`
 * or `\A` and a trailing `$` or `\z` off — so later versions can attach a Zod schema or a
 * value-resolver target keyed off the same {@see \PTGS\TypeBridge\Routing\PathParam} pattern,
 * without changing this object's consumers.
 *
 * `utf8` is the route's own `utf8` option when it states one — whether the router matches the
 * requirement by character (PCRE `u`) or by byte — and null when it leaves it to the router's default.
 */
final readonly class CollectedPathParam
{
    public function __construct(
        public string $name,
        public string $tsType,
        public ?string $requirement = null,
        public ?bool $utf8 = null,
    ) {}
}
