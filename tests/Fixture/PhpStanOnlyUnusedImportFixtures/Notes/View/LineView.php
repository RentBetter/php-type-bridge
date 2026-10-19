<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\PhpStanOnlyUnusedImportFixtures\Notes\View;

use PTGS\TypeBridge\Tests\Fixture\PhpStanOnlyUnusedImportFixtures\Notes\Model\RowSource;

/**
 * Imports an alias kept for PHPStan, for its own code; what it emits never names it.
 *
 * @phpstan-import-type Row from RowSource
 * @phpstan-type Line = array{
 *     label: string,
 * }
 */
final class LineView {}
