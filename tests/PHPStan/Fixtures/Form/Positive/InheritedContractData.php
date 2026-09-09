<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Positive;

/**
 * @phpstan-type _self = array{
 *     label: string,
 * }
 */
final class InheritedContractData
{
    public ?string $label = null;
}
