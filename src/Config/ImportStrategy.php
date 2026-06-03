<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Config;

/**
 * How a per-domain module imports the shared root common module.
 */
enum ImportStrategy: string
{
    /** A relative path up to the output root, e.g. `../../genTypes`. */
    case RelativeSibling = 'relativeSibling';

    /** A fixed module alias, e.g. `@/api/genTypes`. */
    case Alias = 'alias';
}
