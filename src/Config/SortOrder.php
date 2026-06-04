<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Config;

use PTGS\TypeBridge\Sorting\AlphabeticalOrder;
use PTGS\TypeBridge\Sorting\DeclaredOrder;
use PTGS\TypeBridge\Sorting\SortStrategy;

/**
 * Config-facing selector for an ordering strategy applied to emitted declarations
 * and import members. Maps to a {@see SortStrategy}; add a case here (and a class
 * in the Sorting namespace) to offer another built-in strategy.
 */
enum SortOrder: string
{
    /** Preserve the order in which items were produced/collected. */
    case Declared = 'declared';

    /** Sort alphabetically by name. */
    case Name = 'name';

    public function strategy(): SortStrategy
    {
        return match ($this) {
            self::Declared => new DeclaredOrder(),
            self::Name => new AlphabeticalOrder(),
        };
    }
}
