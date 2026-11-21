<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedSelfFixtures\Orders;

/**
 * @phpstan-type _self = array{
 *     address?: ShippingAddress,
 *     hidden?: Hidden,
 *     maybe: Maybe,
 * }
 */
final class OrderData
{
    public ?ShippingAddress $address = null;

    public ?Hidden $hidden = null;

    public ?Maybe $maybe = null;
}
