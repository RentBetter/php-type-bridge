<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * A key typed with an alias of `?string`: a key left out when null, the alias emitted `string`. It
 * admits no null sent, so the key is one a caller must send, as `product: string` would be.
 *
 * @phpstan-type SomeProduct = ?string
 * @phpstan-type _self = array{
 *     product: SomeProduct,
 * }
 */
final class LeftOutProductFilterData
{
    public ?string $product = null;
}
