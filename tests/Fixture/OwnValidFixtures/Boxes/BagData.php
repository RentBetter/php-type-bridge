<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OwnValidFixtures\Boxes;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @phpstan-type _self = array{
 *     label?: string,
 *     lid?: LidData,
 * }
 */
final class BagData
{
    public ?string $label = null;

    // Left out, null — its bag not required — which this refuses.
    #[Assert\NotNull]
    public ?LidData $lid = null;
}
