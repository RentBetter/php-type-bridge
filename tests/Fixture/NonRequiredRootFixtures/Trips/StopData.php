<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\NonRequiredRootFixtures\Trips;

/**
 * @phpstan-type _self = array{
 *     name?: string,
 * }
 */
final class StopData
{
    public ?string $name = null;
}
