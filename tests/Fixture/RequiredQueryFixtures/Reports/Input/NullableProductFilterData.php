<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * A key that is always there and whose value is `?T` — emitted `product?: string`, left off the
 * wire when null — so a caller may leave it out.
 *
 * @phpstan-type _self = array{
 *     product: ?string,
 * }
 */
final class NullableProductFilterData
{
    public ?string $product = null;
}
