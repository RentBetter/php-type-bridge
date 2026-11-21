<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RecursiveFanOutFixtures\Stock;

/**
 * Thirty shelves of forty racks of a category each: what a shelf is, a rack and a category, is
 * the same each time.
 * `memo`, read last, may be cleared.
 *
 * @phpstan-type Memo = string|null
 * @phpstan-type _self = array{
 *     s1?: ShelfData,
 *     s2?: ShelfData,
 *     s3?: ShelfData,
 *     s4?: ShelfData,
 *     s5?: ShelfData,
 *     s6?: ShelfData,
 *     s7?: ShelfData,
 *     s8?: ShelfData,
 *     s9?: ShelfData,
 *     s10?: ShelfData,
 *     s11?: ShelfData,
 *     s12?: ShelfData,
 *     s13?: ShelfData,
 *     s14?: ShelfData,
 *     s15?: ShelfData,
 *     s16?: ShelfData,
 *     s17?: ShelfData,
 *     s18?: ShelfData,
 *     s19?: ShelfData,
 *     s20?: ShelfData,
 *     s21?: ShelfData,
 *     s22?: ShelfData,
 *     s23?: ShelfData,
 *     s24?: ShelfData,
 *     s25?: ShelfData,
 *     s26?: ShelfData,
 *     s27?: ShelfData,
 *     s28?: ShelfData,
 *     s29?: ShelfData,
 *     s30?: ShelfData,
 *     memo?: Memo,
 * }
 */
final class StockInput
{
    public ?string $memo = null;
}
