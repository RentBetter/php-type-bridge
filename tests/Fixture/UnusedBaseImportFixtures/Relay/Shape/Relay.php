<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\UnusedBaseImportFixtures\Relay\Shape;

use PTGS\TypeBridge\Tests\Fixture\UnusedBaseImportFixtures\Common\Shape\Shapes;

/**
 * Imports Common's `Working` for its own code, and declares none: no module emits a `Working` of
 * Relay's.
 *
 * @phpstan-import-type Working from Shapes
 */
final class Relay {}
