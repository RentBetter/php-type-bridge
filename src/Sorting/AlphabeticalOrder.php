<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Sorting;

/**
 * Sorts items alphabetically by their sort key. Items with a null key sort as if
 * empty; since PHP's usort is stable, a group of null-keyed items (e.g. a curated
 * common module) keeps its declared order.
 */
final class AlphabeticalOrder implements SortStrategy
{
    public function sort(array $items, callable $keyOf): array
    {
        usort($items, static fn (mixed $a, mixed $b): int => ($keyOf($a) ?? '') <=> ($keyOf($b) ?? ''));

        return $items;
    }
}
