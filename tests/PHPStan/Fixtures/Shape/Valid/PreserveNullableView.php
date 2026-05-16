<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Valid;

/**
 * `archivedAt` is listed in preserveNull config so the wire emits null when not archived.
 *
 * @phpstan-type _self = array{
 *     id: string,
 *     archivedAt: string|null,
 * }
 */
final class PreserveNullableView
{
}
