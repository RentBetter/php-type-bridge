<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\PHPStan\Fixtures\Shape\Valid;

/**
 * External-system integer ids are not covered by the rule (value type is not string).
 *
 * @phpstan-type _self = array{
 *     id: string,
 *     externalNumericId: int,
 *     sequenceId: int,
 * }
 */
final class NonStringIdFieldView
{
}
