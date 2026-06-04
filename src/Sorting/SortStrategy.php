<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Sorting;

/**
 * Orders a list of items (declarations, import members, ...) by a sort key.
 *
 * Implementations are swappable: a consumer can plug in a different ordering by
 * providing another SortStrategy without touching the assembler or emitter.
 */
interface SortStrategy
{
    /**
     * @template TItem
     * @param list<TItem>               $items
     * @param callable(TItem): ?string  $keyOf extracts an item's sort key (null = no key)
     * @return list<TItem>
     */
    public function sort(array $items, callable $keyOf): array;
}
