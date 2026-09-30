<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Positive;

/**
 * @phpstan-type _self = array{
 *     states: list<value-of<AdvancedState>>,
 * }
 */
final class PromotedMultipleEnumRequestData
{
    /**
     * @param list<AdvancedState> $states
     */
    public function __construct(
        public array $states = [],
    ) {}
}
