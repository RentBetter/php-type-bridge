<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\UnloadableSelfImportFixtures\DevOnly;

/**
 * Its declaration throws without a require-dev package (the interface exists nowhere), and its
 * `_self` is imported elsewhere.
 *
 * @phpstan-type _self = array{x: int}
 */
final class DevOnlyNote implements NotInstalledInterface
{
}
