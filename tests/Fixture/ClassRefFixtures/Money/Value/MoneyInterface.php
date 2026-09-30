<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassRefFixtures\Money\Value;

/**
 * What every amount serialises to.
 *
 * @phpstan-type _self = array{value: string, formatted: string}
 */
interface MoneyInterface extends \JsonSerializable {}
