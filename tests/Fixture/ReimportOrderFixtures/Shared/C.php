<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ReimportOrderFixtures\Shared;

use PTGS\TypeBridge\Tests\Fixture\ReimportOrderFixtures\Common\D;

/**
 * Only re-imports `Bar`.
 *
 * @phpstan-import-type Bar from D
 * @phpstan-type Unrelated = array{x: int}
 */
final class C
{
}
