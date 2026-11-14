<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\PhpStanOnlyImportedFixtures\Notes\View;

use PTGS\TypeBridge\Tests\Fixture\PhpStanOnlyImportedFixtures\Notes\Model\RowSource;

/**
 * @phpstan-import-type Row from RowSource
 * @phpstan-type Line = array{
 *     row: Row,
 * }
 */
final class LineView {}
