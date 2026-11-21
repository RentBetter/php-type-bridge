<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\NonRequiredRootFixtures\Trips;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @phpstan-type _self = array{
 *     note?: string,
 *     stop?: StopData,
 * }
 */
final class LegData
{
    #[Assert\NotBlank]
    public ?string $note = null;

    // Left out, null — its form not required, the trip's not being — which this refuses.
    #[Assert\NotNull]
    public ?StopData $stop = null;
}
