<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\NonRequiredRootFixtures\Trips;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @phpstan-type _self = array{
 *     leg?: LegData,
 *     legs?: list<LegData>,
 * }
 */
final class TripData
{
    // Cascades, but left out it is null: its form is not required, its parent not being.
    #[Assert\Valid]
    public ?LegData $leg = null;

    /** @var list<LegData> */
    #[Assert\Valid]
    public array $legs = [];
}
