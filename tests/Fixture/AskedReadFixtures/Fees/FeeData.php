<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\AskedReadFixtures\Fees;

/**
 * @phpstan-type _self = array{
 *     amount?: Money,
 * }
 */
final class FeeData
{
    public ?string $amount = null;
}
