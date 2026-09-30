<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ClassRefFixtures\Reports\Service;

use PTGS\TypeBridge\Attribute\PhpStanOnly;

/**
 * Working data and the shape it is sent as, side by side: only the first is PHPStan-only.
 *
 * @phpstan-type Occupancy = array{from: \DateTimeImmutable}
 * @phpstan-type OccupancySerialised = array{from: string}
 */
#[PhpStanOnly(['Occupancy'])]
final class OccupancyModel {}
