<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * A key typed with an alias that admits null: what the alias admits, the key does. A query string
 * sends its null by leaving the key out, as it would for `string|null` written out.
 *
 * @phpstan-type MaybeProduct = string|null
 * @phpstan-type _self = array{
 *     product: MaybeProduct,
 * }
 */
final class AliasedProductFilterData
{
    public ?string $product = null;
}
