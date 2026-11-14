<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LeftOutFixtures\Orders;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @phpstan-type _self = array{
 *     street?: string,
 * }
 */
final class ShippingData
{
    #[Assert\NotNull]
    public ?string $street = null;
}
