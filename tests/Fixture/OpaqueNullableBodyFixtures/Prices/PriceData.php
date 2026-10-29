<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OpaqueNullableBodyFixtures\Prices;

/**
 * Body keys typed `Money|null` and `Money|int|null`: clearable by their explicit null, whatever
 * Money admits.
 *
 * @phpstan-type _self = array{
 *     price: Money|null,
 *     discount: Money|int|null,
 *     extras?: array<string, Money>,
 * }
 */
final class PriceData
{
    public ?string $title = null;

    public ?string $price = null;

    public ?string $discount = null;

    /** @var array<string, string> */
    public array $extras = [];
}
