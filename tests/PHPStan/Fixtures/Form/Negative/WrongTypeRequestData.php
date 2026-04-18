<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Negative;

/**
 * @phpstan-type _self = array{
 *     count: int,
 * }
 */
final class WrongTypeRequestData
{
    public ?string $count = null;
}
