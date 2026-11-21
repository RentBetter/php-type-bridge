<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative;

/**
 * An interface's shape is emitted as a class's is: `T|null` on `nickname`, which is NOT in
 * preserveNull config — should be flagged.
 *
 * @phpstan-type _self = array{
 *     id: string,
 *     nickname: string|null,
 * }
 */
interface MissingPreserveNullContract
{
}
