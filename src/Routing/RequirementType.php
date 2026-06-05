<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Routing;

use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Maps a route-parameter requirement (a regex) to the TypeScript type emitted for that
 * path parameter. Seeded from Symfony's own {@see Requirement} constants so the standard
 * id/number/slug patterns refine without configuration.
 *
 * A consuming project extends the map for its own patterns — e.g. property-api's short
 * (8-char hex) or short-or-full uuid, which Symfony's Requirement does not cover — via the
 * `requirementTypes` key of its TypeBridge config (regex => TS type, merged over these
 * defaults). An unknown or absent requirement falls back to `string`.
 *
 * The raw requirement is retained on {@see \PTGS\TypeBridge\Model\CollectedPathParam}, so the
 * same entries can later drive a Zod schema and a value-resolver target without any consumer
 * change.
 */
final class RequirementType
{
    /**
     * @return array<string, string> requirement regex => TS type
     */
    public static function defaults(): array
    {
        return [
            Requirement::DIGITS => 'number',
            Requirement::POSITIVE_INT => 'number',
            Requirement::UUID => 'string',
            Requirement::UID_RFC4122 => 'string',
            Requirement::ULID => 'string',
            Requirement::UID_BASE32 => 'string',
            Requirement::UID_BASE58 => 'string',
            Requirement::MONGODB_ID => 'string',
            Requirement::ASCII_SLUG => 'string',
            Requirement::DATE_YMD => 'string',
        ];
    }
}
