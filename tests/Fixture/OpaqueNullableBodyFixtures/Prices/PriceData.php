<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OpaqueNullableBodyFixtures\Prices;

/**
 * A body key typed `Money|null`: clearable by its explicit null, whatever Money admits.
 *
 * @phpstan-type _self = array{
 *     price: Money|null,
 * }
 */
final class PriceData
{
    public ?string $title = null;

    public ?string $price = null;
}
