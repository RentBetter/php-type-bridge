<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\LeftOutFixtures\Orders;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @phpstan-type _self = array{
 *     product?: string,
 * }
 */
final class OrderFilter
{
    #[Assert\NotBlank]
    public ?string $product = null;
}
