<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * The same, through an alias of its own naming one it imports.
 *
 * @phpstan-import-type MaybeProduct from AliasedProductFilterData
 * @phpstan-type Product = MaybeProduct
 * @phpstan-type _self = array{
 *     product: Product,
 * }
 */
final class ImportedProductFilterData
{
    public ?string $product = null;
}
