<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ReimportOrderFixtures\Notes;

use PTGS\TypeBridge\Tests\Fixture\ReimportOrderFixtures\Shared\C;

/**
 * Read first: following `Foo` reads B, which imports `Bar` back from here while this is still being followed.
 *
 * @phpstan-import-type Foo from B
 * @phpstan-import-type Bar from C
 * @phpstan-type Uses = array{foo: Foo, bar: Bar}
 */
final class A
{
}
