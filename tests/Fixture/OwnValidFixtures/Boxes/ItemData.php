<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OwnValidFixtures\Boxes;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @phpstan-type _self = array{
 *     x?: string,
 * }
 */
final class ItemData
{
    #[Assert\NotBlank]
    public ?string $x = null;
}
