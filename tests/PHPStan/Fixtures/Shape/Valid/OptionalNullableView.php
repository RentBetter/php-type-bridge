<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Valid;

/**
 * @phpstan-type _self = array{
 *     id: string,
 *     nickname: ?string,
 *     description: ?string,
 * }
 */
final class OptionalNullableView
{
}
