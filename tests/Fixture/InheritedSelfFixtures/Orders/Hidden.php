<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\InheritedSelfFixtures\Orders;

use PTGS\TypeBridge\Attribute\PhpStanOnly;

/**
 * Its `_self` is PHPStan's alone: never emitted, so no shape of a tool either.
 *
 * @phpstan-type _self = array{
 *     secret?: string,
 * }
 */
#[PhpStanOnly(['_self'])]
final class Hidden
{
    public ?string $secret = null;
}
