<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OpaqueQueryAliasFixtures\Prices;

/**
 * A query: a key that may be left out, and a list's items and a map's values, which no query
 * string sends a null for, and one an explicit null lets be left out — nothing turns on whether
 * Money admits null.
 *
 * @phpstan-type _self = array{
 *     title: string,
 *     price?: Money,
 *     bands: list<Money>,
 *     byCurrency: array<string, Money>,
 *     floor: Money|null,
 * }
 */
final class PriceData
{
    public ?string $title = null;

    public ?string $price = null;

    /** @var list<string> */
    public array $bands = [];

    /** @var array<string, string> */
    public array $byCurrency = [];

    public ?string $floor = null;
}
