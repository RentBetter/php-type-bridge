<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\UnloadableFixtures\DevOnly;

/**
 * Stands in for a class a project keeps under src/ for tooling only — a PHPStan rule whose
 * interfaces come from a require-dev package. In an install without that package the
 * declaration itself throws (Interface "…" not found), which is what the collectors have
 * to survive. The interface below deliberately exists nowhere.
 */
final class DevOnlyRule implements NotInstalledInterface
{
}
