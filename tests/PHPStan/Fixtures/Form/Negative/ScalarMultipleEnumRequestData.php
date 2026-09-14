<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Negative;

use PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Positive\AdvancedState;

/**
 * @phpstan-type _self = array{
 *     states: value-of<AdvancedState>,
 * }
 */
final class ScalarMultipleEnumRequestData
{
    public ?AdvancedState $states = null;
}
