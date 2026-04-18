<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Negative;

/**
 * @phpstan-type _self = array{
 *     ?ownerId: string,
 * }
 */
final class MissingPropertyRequestData
{
    public ?string $title = null;
}
