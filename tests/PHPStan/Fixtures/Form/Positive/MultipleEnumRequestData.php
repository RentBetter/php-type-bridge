<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Positive;

/**
 * @phpstan-type _self = array{
 *     states: list<value-of<AdvancedState>>,
 * }
 */
final class MultipleEnumRequestData
{
    /** @var list<AdvancedState> */
    public array $states = [];
}
