<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OpaqueQueryAliasFixtures\Prices;

/**
 * A path: no segment is ever null, so nothing turns on Money's.
 *
 * @phpstan-type _self = array{
 *     id: Money,
 * }
 */
final class PricePath
{
    public string $id = '';
}
