<?php

declare(strict_types=1);

namespace PTGS\TypeBridge\Tests\Fixture\ConfigAliasImportFixtures\Journals;

use PTGS\TypeBridge\Tests\Fixture\ConfigAliasImportFixtures\Calendar\CalendarTypes;

/**
 * Calendar's `Date` imported where no project-wide alias is declared: under another name all the
 * same, a name an alias's TypeScript uses being reserved in every module.
 *
 * @phpstan-import-type Date from CalendarTypes
 * @phpstan-type Entry = array{on: Date}
 */
final class JournalView {}
