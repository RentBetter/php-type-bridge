<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Negative;

/**
 * @phpstan-type _self = array{
 *     id: string,
 *     projectId: string,
 *     authorId: string,
 * }
 */
final class HasIdSuffixView
{
}
