<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedSelfFixtures\Orders;

/**
 * A `_self` of `?T` is `T`, as an alias's is.
 *
 * @phpstan-type _self = ?array{a: string}
 */
final class Maybe
{
    public ?string $a = null;
}
