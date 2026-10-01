<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\SelfImportFixtures\Money\Value;

/**
 * An interface with a looser shape of its own, which a class that declares its own must not fall back to.
 *
 * @phpstan-type _self = array{value: string, ...}
 */
interface MoneyLike
{
}
