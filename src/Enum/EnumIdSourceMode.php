<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Enum;

/**
 * Pure-reflection strategies for deriving an enum case's id literal — no method
 * execution, so they preserve TypeBridge's static guarantee.
 */
enum EnumIdSourceMode
{
    /** The case name verbatim, e.g. `ACTIVE`. */
    case CaseName;

    /** The string backing value, e.g. `active`. Requires a string-backed enum. */
    case BackingValue;

    /** The enum's `ID_PREFIX` constant (when a string) prepended to the case name. */
    case PrefixedCaseName;
}
