<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative;

/**
 * A trait's alias is emitted as a class's is: `T|null` on `nickname`, which is NOT in
 * preserveNull config — should be flagged.
 *
 * @phpstan-type SharedNickname = array{
 *     id: string,
 *     nickname: string|null,
 * }
 */
trait MissingPreserveNullTrait
{
}
