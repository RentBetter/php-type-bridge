<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RootQueryNamedFixtures\Shared;

/**
 * @phpstan-type _self = array{q?: string}
 */
final class SharedFilterData
{
    public ?string $q = null;
}
