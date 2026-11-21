<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OpaqueBodyAliasFixtures\Prices;

/**
 * @phpstan-type _self = array{
 *     price?: Money,
 * }
 */
final class PriceData
{
    public ?string $title = null;

    public ?string $price = null;
}
