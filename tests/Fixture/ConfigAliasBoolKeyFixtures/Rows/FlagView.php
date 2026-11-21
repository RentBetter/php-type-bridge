<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ConfigAliasBoolKeyFixtures\Rows;

/**
 * Maps keyed by what no array is: a project-wide alias of a boolean, and a module's.
 *
 * @phpstan-type Local = bool
 * @phpstan-type Flagged = array{byFlag: array<Flag, string>, byLocal: array<Local, string>}
 */
final class FlagView {}
