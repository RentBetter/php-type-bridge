<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\PhpStanOnlyImportedFixtures\Notes\Model;

use PTGS\TypeBridge\Attribute\PhpStanOnly;

/**
 * @phpstan-type Row = array{
 *     a: int,
 * }
 */
#[PhpStanOnly]
final class RowSource {}
