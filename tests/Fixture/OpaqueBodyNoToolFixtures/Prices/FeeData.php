<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OpaqueBodyNoToolFixtures\Prices;

/**
 * @phpstan-type _self = array{
 *     fee?: Money,
 * }
 */
final class FeeData
{
    public ?string $fee = null;
}
