<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\PartlyQualifiedFixtures\Orders;

use PTGS\TypeBridge\Tests\Fixture\PartlyQualifiedFixtures\Orders\Input as Parts;

/**
 * Each key names AddressData another way PHP resolves: from this namespace, through a `use` of its
 * own, and in full.
 *
 * @phpstan-type _self = array{
 *     address?: Input\AddressData,
 *     billing?: Parts\AddressData,
 *     shipping?: \PTGS\TypeBridge\Tests\Fixture\PartlyQualifiedFixtures\Orders\Input\AddressData,
 * }
 */
final class OrderInput
{
    public ?Input\AddressData $address = null;

    public ?Input\AddressData $billing = null;

    public ?Input\AddressData $shipping = null;
}
