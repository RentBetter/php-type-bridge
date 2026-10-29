<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\AskedReadFixtures\Fees;

/**
 * @phpstan-type _self = array{
 *     fee?: FeeData,
 * }
 */
final class PriceData
{
    public ?string $fee = null;
}
