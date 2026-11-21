<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ReimportOrderFixtures\Notes;

use PTGS\TypeBridge\Tests\Fixture\ReimportOrderFixtures\Common\E;

/**
 * @phpstan-import-type Foo from E
 * @phpstan-import-type Bar from A
 * @phpstan-type BUses = array{bar: Bar}
 */
final class B
{
}
