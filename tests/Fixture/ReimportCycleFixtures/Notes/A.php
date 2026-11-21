<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ReimportCycleFixtures\Notes;

use PTGS\TypeBridge\Tests\Fixture\ReimportCycleFixtures\Common\D;

/**
 * Its `X` is B's, which B imports from here as `V`: this file's `V`, from D, which has it from E.
 *
 * @phpstan-import-type X from B
 * @phpstan-import-type V from D
 */
final class A
{
}
