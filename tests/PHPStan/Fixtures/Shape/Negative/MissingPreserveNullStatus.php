<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative;

/**
 * An enum's `_self`: its shape is emitted `MissingPreserveNullStatusData`.
 *
 * @phpstan-type _self = array{
 *     id: string,
 *     hint: string|null,
 * }
 */
enum MissingPreserveNullStatus: string
{
    case Open = 'open';
}
