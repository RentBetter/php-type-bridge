<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Sorting;

/**
 * Preserves the order items were produced/collected in.
 */
final class DeclaredOrder implements SortStrategy
{
    public function sort(array $items, callable $keyOf): array
    {
        return $items;
    }
}
