<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\IntEnumTypedFixtures\Notes;

/**
 * Int-backed, with a shape of its own: sent as that.
 *
 * @phpstan-type _self = array{
 *     rank: int,
 * }
 */
enum Level: int
{
    case One = 1;
}
