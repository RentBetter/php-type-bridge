<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedSelfFixtures\Orders;

/**
 * @phpstan-type _self = array{
 *     street?: string,
 * }
 */
class Address
{
    public ?string $street = null;
}
