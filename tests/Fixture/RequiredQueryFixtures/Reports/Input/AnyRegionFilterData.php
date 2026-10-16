<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * Inherits a key whose value may be null: left out, as an own one may be.
 *
 * @phpstan-type RegionScope = array{
 *     region: string|null,
 * }
 * @phpstan-type _self = RegionScope & array{
 *     page?: int,
 * }
 */
final class AnyRegionFilterData
{
    public ?string $region = null;
    public ?int $page = null;
}
