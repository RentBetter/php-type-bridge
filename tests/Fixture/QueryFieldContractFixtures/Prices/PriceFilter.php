<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\QueryFieldContractFixtures\Prices;

/**
 * @phpstan-type _self = array{
 *     price?: PriceData,
 * }
 */
final class PriceFilter
{
    public ?PriceData $price = null;
}
