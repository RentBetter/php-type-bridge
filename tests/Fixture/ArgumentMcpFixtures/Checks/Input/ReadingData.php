<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Checks\Input;

use PTGS\TypeBridge\Tests\Fixture\ArgumentMcpFixtures\Common\Spec\Param;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @phpstan-type _self = array{
 *     label: string,
 *     value: string,
 * }
 */
final class ReadingData
{
    #[Param('What was measured.')]
    #[Assert\Length(max: 80)]
    public ?string $label = null;

    #[Param(['The figure as read.', 'With its unit, where it has one.'])]
    public ?string $value = null;
}
