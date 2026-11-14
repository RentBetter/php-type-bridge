<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OwnValidFixtures\Boxes;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @phpstan-type _self = array{
 *     y?: string,
 * }
 */
final class LidData
{
    #[Assert\NotBlank]
    public ?string $y = null;
}
