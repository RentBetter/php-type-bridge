<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\OwnValidFixtures\Boxes;

/**
 * @phpstan-type _self = array{
 *     lid?: LidData,
 * }
 */
final class ShellData
{
    public ?LidData $lid = null;
}
