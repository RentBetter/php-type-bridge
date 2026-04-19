<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative;

/**
 * @phpstan-type _self = array{
 *     id: string,
 *     entityType: string,
 *     entityId: string,
 *     body: string,
 * }
 */
final class HasTypeIdPairView
{
}
