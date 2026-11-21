<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\RequiredQueryFixtures\Reports\Input;

/**
 * Narrows a key it inherits: its interface still extends the shape it inherits it from.
 *
 * @phpstan-import-type RegionScope from AnyRegionFilterData
 * @phpstan-type _self = RegionScope & array{
 *     region: 'north'|null,
 *     page?: int,
 * }
 */
final class NorthRegionFilterData
{
    public ?string $region = null;
    public ?int $page = null;
}
