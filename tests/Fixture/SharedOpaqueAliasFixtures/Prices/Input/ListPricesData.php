<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\SharedOpaqueAliasFixtures\Prices\Input;

/**
 * @phpstan-import-type PriceInput from PriceShapes
 * @phpstan-type _self = PriceInput
 */
final class ListPricesData
{
    public ?string $amount = null;
}
