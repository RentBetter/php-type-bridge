<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Form\Negative;

/**
 * @phpstan-type _self = array{
 *     title: string,
 * }
 */
final class MismatchedRequestData
{
    public ?string $title = null;
}
