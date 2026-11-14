<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * What every product-scoped filter carries.
 *
 * @phpstan-type _self = array{
 *     product: string,
 * }
 */
final class ProductScopeData
{
    public ?string $product = null;
}
