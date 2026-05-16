<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative;

/**
 * `archivedAt` is in preserveNull config but is annotated `?T` instead of `T|null` —
 * should be flagged.
 *
 * @phpstan-type _self = array{
 *     id: string,
 *     archivedAt: ?string,
 * }
 */
final class UnnecessaryOptionalView
{
}
