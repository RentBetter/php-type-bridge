<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative;

/**
 * Uses `T|null` annotations but `nickname` is NOT in preserveNull config —
 * should be flagged.
 *
 * @phpstan-type _self = array{
 *     id: string,
 *     nickname: string|null,
 * }
 */
final class MissingPreserveNullView
{
}
