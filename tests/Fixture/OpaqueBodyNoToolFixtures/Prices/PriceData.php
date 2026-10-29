<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OpaqueBodyNoToolFixtures\Prices;

/**
 * @phpstan-type _self = array{
 *     price?: Money,
 *     fee?: FeeData,
 * }
 */
final class PriceData
{
    public ?string $title = null;

    public ?string $price = null;

    public ?FeeData $fee = null;
}
