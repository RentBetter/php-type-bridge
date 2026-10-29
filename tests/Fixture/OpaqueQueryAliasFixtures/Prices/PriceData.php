<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OpaqueQueryAliasFixtures\Prices;

/**
 * @phpstan-type _self = array{
 *     title: string,
 *     price?: Money,
 * }
 */
final class PriceData
{
    public ?string $title = null;

    public ?string $price = null;
}
