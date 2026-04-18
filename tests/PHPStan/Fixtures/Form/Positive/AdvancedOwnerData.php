<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Positive;

/**
 * @phpstan-type _self = array{
 *     name: string,
 * }
 */
final class AdvancedOwnerData
{
    public ?string $name = null;
}
