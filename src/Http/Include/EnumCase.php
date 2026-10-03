<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Http\Include;

use BackedEnum;
use PTGS\TypeBridge\Enum\ApiEnum;

/**
 * An enum in a normalised shape. An {@see ApiEnum} is sent as the object it serialises to, or as
 * its bare id to a request that asks for ids (`X-Enum-Format: id`); an expand naming its path
 * sends it whole either way, or cut to a field list (`checks.status(name)`). Any other backed
 * enum is sent as its value. Made by IncludeMarkers::enum(); written out by IncludeResolver. A
 * shape declares such a key as `enum<Status>`, and TypeBridge emits the enum's own type.
 *
 * @template-covariant T of ApiEnum|BackedEnum
 */
final readonly class EnumCase
{
    /**
     * @param T $case
     */
    public function __construct(
        public ApiEnum|BackedEnum $case,
    ) {}
}
