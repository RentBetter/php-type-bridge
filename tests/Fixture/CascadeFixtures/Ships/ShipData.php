<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\CascadeFixtures\Ships;

use PTGS\TypeBridge\Tests\Fixture\EmbeddedFormFixtures\Contacts\Input\PostalData;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Cascades into every object it holds: PostalData's own constraints are validated.
 *
 * @phpstan-type _self = array{
 *     postal?: PostalData,
 * }
 */
#[Assert\Cascade]
final class ShipData
{
    public ?PostalData $postal = null;
}
