<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative;

use PTGS\TypeBridge\Attribute\ValueOfName;

/**
 * An enum whose value union is named otherwise: its `_self` takes the enum's own name.
 *
 * @phpstan-type _self = array{
 *     id: string,
 *     hint: string|null,
 * }
 */
#[ValueOfName('MissingPreserveNullNamedStatusId')]
enum MissingPreserveNullNamedStatus: string
{
    case Open = 'open';
}
