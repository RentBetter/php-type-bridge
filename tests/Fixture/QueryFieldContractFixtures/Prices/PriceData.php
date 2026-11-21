<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\QueryFieldContractFixtures\Prices;

/**
 * Its `amount` is a Money, whose null the config's TypeScript (`Dinero`) can't say.
 *
 * @phpstan-type _self = array{
 *     amount: Money,
 * }
 */
final class PriceData
{
}
