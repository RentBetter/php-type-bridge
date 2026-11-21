<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\UnloadableImportFixtures\DevOnly;

/**
 * A class kept under src/ whose declaration needs a require-dev package (see UnloadableFixtures'
 * DevOnlyRule): in an install without it, loading it throws. It declares an alias another class
 * imports. The interface below deliberately exists nowhere.
 *
 * @phpstan-type Code = string
 */
final class DevOnlyCodes implements NotInstalledInterface
{
}
