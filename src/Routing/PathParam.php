<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Routing;

/**
 * Route-requirement constants that carry both the Symfony regex AND a refined
 * TypeBridge type for the matching path parameter:
 *
 *   #[Route('/accounts/{accountId}', requirements: ['accountId' => PathParam::UUID])]
 *
 * The codegen reads the route's {placeholders} and types each one as a plain
 * `string` unless its requirement matches one of these patterns, in which case
 * {@see PathParam::tsType()} supplies the refined type. The constants are plain
 * strings so they are legal in attribute arguments and drop in exactly where a
 * raw regex used to go.
 *
 * This is the single declaration the bundle fans out from: the route regex today,
 * the generated TS type today, and — keyed off the same constants — a generated
 * Zod schema and a value-resolver target in later versions.
 */
final class PathParam
{
    public const string UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
    public const string ULID = '[0-7][0-9A-HJKMNP-TV-Z]{25}';
    public const string INT = '\d+';

    /**
     * Refined TS type for a path parameter given its route requirement, or `string`
     * when the requirement is absent/unrecognised. The seam for branded id types and
     * Zod schemas later — for now UUID/ULID stay `string` and only INT refines.
     */
    public static function tsType(?string $requirement): string
    {
        return match ($requirement) {
            self::INT => 'number',
            default => 'string',
        };
    }
}
