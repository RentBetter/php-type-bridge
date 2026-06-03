<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Config;

/**
 * How a domain name maps to its output directory path.
 */
enum SegmentCase: string
{
    /** Use the domain verbatim, e.g. `Listings/Channels`. */
    case AsIs = 'asIs';

    /** Split on `\` or `/` and lcfirst each segment, e.g. `Listings\Channels` -> `listings/channels`. */
    case PerSegmentLcFirst = 'perSegmentLcFirst';
}
